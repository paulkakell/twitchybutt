<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RequireMfa;
use App\Models\User;
use App\Rules\PasswordBytes;
use App\Services\MfaService;
use App\Services\SessionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class MfaController
{
    public function settings(Request $request): View
    {
        $user = $this->user($request);

        return view('mfa-settings', ['enrolled' => $user->hasMfa(), 'remaining' => DB::table('mfa_recovery_codes')->where('user_id', $user->id)->count()]);
    }

    public function begin(Request $request, MfaService $mfa): View
    {
        $this->password($request);
        $secret = $mfa->begin($this->user($request));

        return view('mfa-enroll', compact('secret'));
    }

    public function replace(Request $request, MfaService $mfa): View
    {
        $this->password($request);
        $user = $this->user($request);
        $verifiedAt = $request->session()->get('mfa_verified_at');
        abort_unless(RequireMfa::satisfied($request, $user) && is_int($verifiedAt) && $verifiedAt <= now()->getTimestamp() && $verifiedAt > now()->subMinutes(5)->getTimestamp(), 403, 'Sign out and complete MFA again before replacing your authenticator.');
        $secret = $mfa->begin($user, true);

        return view('mfa-enroll', compact('secret'));
    }

    public function confirm(Request $request, MfaService $mfa, SessionRegistry $sessions): View
    {
        $this->password($request);
        $code = $request->validate(['code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/']])['code'];
        $snapshot = $this->user($request);
        $codes = $mfa->confirm($snapshot, $code);
        $user = $snapshot->fresh();
        abort_unless($user instanceof User && $user->auth_version === $snapshot->auth_version + 1, 401);
        Auth::guard('web')->setUser($user);
        $request->session()->regenerate(true);
        $sessions->start($request->session(), $user);
        $this->stamp($request, $user);

        return view('mfa-codes', compact('codes'));
    }

    public function form(Request $request): View|RedirectResponse
    {
        $user = $this->user($request);
        if (! $user->hasMfa() || RequireMfa::satisfied($request, $user)) {
            return redirect('/account');
        }

        return view('mfa-challenge');
    }

    public function challenge(Request $request, MfaService $mfa): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:35'], 'recovery' => ['sometimes', 'boolean']]);
        $user = $this->user($request);
        $mfa->challenge($user, $data['code'], (bool) ($data['recovery'] ?? false));
        $request->session()->regenerate(true);
        $this->stamp($request, $user);

        return redirect('/account');
    }

    public function regenerate(Request $request, MfaService $mfa): View
    {
        $this->password($request);
        $code = $request->validate(['code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/']])['code'];
        $codes = $mfa->regenerate($this->user($request), $code);

        return view('mfa-codes', compact('codes'));
    }

    private function password(Request $request): void
    {
        $request->validate(['password' => ['bail', 'required', 'string', new PasswordBytes, 'current_password:web']]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function stamp(Request $request, User $user): void
    {
        $request->session()->put(['mfa_verified_version' => $user->auth_version, 'mfa_verified_user' => (string) $user->id, 'mfa_verified_at' => now()->getTimestamp()]);
    }
}
