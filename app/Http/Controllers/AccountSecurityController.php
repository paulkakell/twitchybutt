<?php

namespace App\Http\Controllers;

use App\Jobs\SendAccountMail;
use App\Models\User;
use App\Rules\PasswordBytes;
use App\Services\AccountSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

final class AccountSecurityController
{
    public const RECOVERY_MESSAGE = 'If the account is eligible, a recovery email will be sent. Check your inbox and spam folder.';

    public function settings(): View
    {
        return view('account-security');
    }

    public function forgot(Request $request): JsonResponse|RedirectResponse
    {
        $this->requireMail();
        $email = $request->input('email');
        if (is_string($email)) {
            $request->merge(['email' => strtolower($email)]);
        }
        $data = $request->validate(['email' => ['bail', 'required', 'string', 'email', 'max:254']]);
        // Do not look up the account on the public request path.
        $this->enqueue(new SendAccountMail($data['email'], 'reset', now()->getTimestamp()));

        return $request->expectsJson() ? response()->json(['message' => self::RECOVERY_MESSAGE], 202) : redirect('/forgot-password')->with('status', self::RECOVERY_MESSAGE);
    }

    public function resend(Request $request): RedirectResponse
    {
        $this->requireMail();
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        if (! $user->hasVerifiedEmail()) {
            $this->enqueue(new SendAccountMail($user->email, 'verify', now()->getTimestamp(), $user->auth_version));
        }

        return redirect('/account/security')->with('status', 'Verification requested. Check your inbox and spam folder.');
    }

    public function verify(Request $request, AccountSecurityService $service, string $id, string $hash, string $generation): RedirectResponse
    {
        $this->requireMail();
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $service->verify($user, $id, $hash, $generation);

        return redirect('/account/security')->with('status', 'Email address verified.');
    }

    public function resetForm(Request $request): View
    {
        $token = $request->query('token');

        return view('password-reset', ['token' => is_string($token) && preg_match('/\A[0-9a-f]{64}\z/', $token) === 1 ? $token : '']);
    }

    public function reset(Request $request, AccountSecurityService $service): RedirectResponse
    {
        $this->requireMail();
        $email = $request->input('email');
        if (is_string($email)) {
            $request->merge(['email' => strtolower($email)]);
        }
        $data = $request->validate([
            'email' => ['bail', 'required', 'string', 'email', 'max:254'],
            'token' => ['bail', 'required', 'string', 'regex:/\A[0-9a-f]{64}\z/'],
            'password' => ['bail', 'required', 'string', new PasswordBytes, 'confirmed', Password::min(12)->letters()->numbers()],
            'password_confirmation' => ['required', 'string'],
        ]);
        if (! $service->reset($data)) {
            throw ValidationException::withMessages(['token' => 'This recovery link is invalid or expired. Request a new link.']);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('status', 'Password changed. Sign in with your new password.');
    }

    private function requireMail(): void
    {
        abort_unless(config('account_security.mail_enabled'), 503, 'Account email is unavailable. Contact this site operator.');
        AccountSecurityService::validateConfiguration();
    }

    private function enqueue(SendAccountMail $job): void
    {
        try {
            Bus::dispatch($job);
        } catch (Throwable $exception) {
            Log::error('cms.mail.enqueue_failed');
            abort(503, 'Account email is temporarily unavailable. Try again later.');
        }
        Log::info('cms.mail.queued');
    }
}
