<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use LogicException;

final class AccountSecurityService
{
    public static function origin(): string
    {
        $origin = rtrim((string) config('app.url'), '/');
        $parts = parse_url($origin);
        if (! is_array($parts) || empty($parts['host']) || isset($parts['user'], $parts['pass']) || isset($parts['user']) || isset($parts['query']) || isset($parts['fragment']) || ! empty($parts['path'])) {
            throw new LogicException('APP_URL must be a canonical origin without credentials, path, query or fragment.');
        }
        $loopback = app()->environment(['local', 'testing']) && in_array($parts['host'], ['localhost', '127.0.0.1', '[::1]'], true);
        if (($parts['scheme'] ?? '') !== 'https' && ! ($loopback && ($parts['scheme'] ?? '') === 'http')) {
            throw new LogicException('Account links require HTTPS except for local loopback development.');
        }

        return $origin;
    }

    public static function validateConfiguration(): void
    {
        if (! config('account_security.mail_enabled')) {
            return;
        }
        self::origin();
        if (config('queue.default') !== 'database' || config('queue.connections.database.driver') !== 'database' || config('queue.failed.driver') !== 'null') {
            throw new LogicException('Account mail requires the encrypted database job path and disabled raw failed-job storage.');
        }
        if (app()->environment('testing') && config('mail.default') === 'array') {
            return;
        }
        $loopback = app()->environment('local') && in_array(config('mail.mailers.smtp.host'), ['localhost', '127.0.0.1', '[::1]'], true);
        if (config('mail.default') !== 'smtp' || (! $loopback && config('mail.mailers.smtp.scheme') !== 'smtps') || ! filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)) {
            throw new LogicException('Account mail requires SMTP with implicit TLS and a valid sender; plain SMTP is local-loopback only.');
        }
    }

    public function verificationUrl(User $user): string
    {
        $path = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email), 'generation' => $user->auth_version], absolute: false);

        return self::origin().$path;
    }

    public function verify(User $user, string $id, string $hash, string $generation): void
    {
        DB::transaction(function () use ($user, $id, $hash, $generation): void {
            $current = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless((string) $current->id === $id && hash_equals(sha1($current->email), $hash) && (string) $current->auth_version === $generation, 403);
            if (! $current->hasVerifiedEmail()) {
                $current->markEmailAsVerified();
                event(new Verified($current));
                Log::info('cms.email.verified', ['actor_id' => $current->id]);
            }
        });
    }

    public function sendRequestedLink(string $email, string $purpose, int $requestedAt, ?int $generation): void
    {
        self::validateConfiguration();
        DB::transaction(function () use ($email, $purpose, $requestedAt, $generation): void {
            $user = User::query()->where('email', $email)->lockForUpdate()->first();
            if ($user === null || ($user->password_changed_at !== null && $user->password_changed_at->getTimestamp() >= $requestedAt)) {
                return;
            }
            if ($purpose === 'verify' && $user->auth_version === $generation && ! $user->hasVerifiedEmail()) {
                $user->sendEmailVerificationNotification();
            } elseif ($purpose === 'reset') {
                // The same user lock serializes issuance and redemption on PostgreSQL.
                Password::broker()->sendResetLink(['email' => $email]);
            }
        });
        Log::info('cms.mail.processed');
    }

    /** @param array{email: string, token: string, password: string, password_confirmation: string} $credentials */
    public function reset(array $credentials): bool
    {
        return (new Timebox)->call(function () use ($credentials): bool {
            return DB::transaction(function () use ($credentials): bool {
                $user = User::query()->where('email', $credentials['email'])->lockForUpdate()->first();
                if ($user === null) {
                    return false;
                }
                $status = Password::broker()->reset($credentials, function (User $account, string $password): void {
                    $account->password = $password;
                    $account->auth_version = $account->auth_version + 1;
                    $account->password_changed_at = now();
                    $account->setRememberToken(Str::random(60));
                    $account->save();
                    event(new PasswordReset($account));
                    Log::info('cms.password.reset', ['actor_id' => $account->id]);
                });

                return $status === Password::PASSWORD_RESET;
            });
        }, 200000);
    }
}
