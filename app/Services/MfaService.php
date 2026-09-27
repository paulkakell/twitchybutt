<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class MfaService
{
    public function __construct(private readonly Totp $totp) {}

    private function current(User $snapshot): User
    {
        $user = User::query()->whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
        abort_unless($user->auth_version === $snapshot->auth_version, 401, 'Please sign in again.');

        return $user;
    }

    public function begin(User $snapshot, bool $replacement = false): string
    {
        return DB::transaction(function () use ($snapshot, $replacement): string {
            $user = $this->current($snapshot);
            abort_unless($user->hasMfa() === $replacement, 409, 'MFA enrollment state changed.');
            $user->mfa_pending_version = $user->auth_version;
            $user->mfa_pending_purpose = $replacement ? 'replace' : 'enroll';
            $secret = $this->totp->secret();
            $user->mfa_pending_secret = $secret;
            $user->mfa_pending_expires_at = now()->addMinutes(10);
            $user->save();

            return $secret;
        }, 5);
    }

    /** @return list<string> */
    public function confirm(User $snapshot, string $code): array
    {
        return DB::transaction(function () use ($snapshot, $code): array {
            $user = $this->current($snapshot);
            if ($user->mfa_pending_version !== $user->auth_version || $user->mfa_pending_purpose !== ($user->hasMfa() ? 'replace' : 'enroll')) {
                $this->invalid();
            }
            $secret = $user->mfa_pending_secret;
            $counter = $secret !== null && $user->mfa_pending_expires_at?->isFuture()
                ? $this->totp->match($secret, $code, now()->getTimestamp(), null) : null;
            if ($counter === null) {
                $this->invalid();
            }
            $user->mfa_secret = $secret;
            $user->mfa_confirmed_at = now();
            $user->mfa_last_counter = $counter;
            $user->mfa_pending_secret = null;
            $user->mfa_pending_expires_at = null;
            $user->mfa_pending_version = null;
            $user->mfa_pending_purpose = null;
            $user->auth_version++;
            $user->save();
            DB::table('account_sessions')->where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $codes = $this->replaceCodes($user);
            DB::afterCommit(fn () => Log::info('cms.mfa.enrolled', ['actor_id' => $user->id]));

            return $codes;
        }, 5);
    }

    public function challenge(User $snapshot, string $code, bool $recovery): void
    {
        DB::transaction(function () use ($snapshot, $code, $recovery): void {
            $user = $this->current($snapshot);
            if (! $user->hasMfa()) {
                $this->invalid();
            }
            if ($recovery) {
                $canonical = strtolower(str_replace('-', '', $code));
                if (preg_match('/\A[0-9a-f]{32}\z/', $canonical) !== 1 || DB::table('mfa_recovery_codes')->where('user_id', $user->id)->where('code_hash', hash('sha256', $canonical))->delete() !== 1) {
                    $this->invalid();
                }
            } else {
                $this->consumeTotp($user, $code);
            }
            DB::afterCommit(fn () => Log::info('cms.mfa.verified', ['actor_id' => $user->id]));
        }, 5);
    }

    /** @return list<string> */
    public function regenerate(User $snapshot, string $code): array
    {
        return DB::transaction(function () use ($snapshot, $code): array {
            $user = $this->current($snapshot);
            if (! $user->hasMfa()) {
                $this->invalid();
            }
            $this->consumeTotp($user, $code);
            $codes = $this->replaceCodes($user);
            DB::afterCommit(fn () => Log::info('cms.mfa.codes_rotated', ['actor_id' => $user->id]));

            return $codes;
        }, 5);
    }

    private function consumeTotp(User $user, string $code): void
    {
        $counter = $this->totp->match((string) $user->mfa_secret, $code, now()->getTimestamp(), $user->mfa_last_counter);
        if ($counter === null) {
            $this->invalid();
        }
        $user->mfa_last_counter = $counter;
        $user->save();
    }

    /** @return list<string> */
    private function replaceCodes(User $user): array
    {
        DB::table('mfa_recovery_codes')->where('user_id', $user->id)->delete();
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $raw = bin2hex(random_bytes(16));
            DB::table('mfa_recovery_codes')->insert(['user_id' => $user->id, 'code_hash' => hash('sha256', $raw), 'created_at' => now()]);
            $codes[] = implode('-', str_split($raw, 8));
        }

        return $codes;
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['code' => 'The code is invalid, expired or already used.']);
    }
}
