<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\PasswordBytes;
use App\Services\SessionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController
{
    public function register(Request $request): RedirectResponse
    {
        $email = $request->input('email');
        if (is_string($email)) {
            $request->merge(['email' => strtolower($email)]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['bail', 'required', 'string', 'email', 'max:254', 'unique:users,email'],
            'password' => ['bail', 'required', 'string', new PasswordBytes, 'confirmed', Password::min(12)->letters()->numbers()],
        ]);
        $user = User::query()->create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        Auth::login($user);
        $request->session()->regenerate();
        Log::info('cms.member.registered', ['actor_id' => $user->id]);

        return redirect('/account');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['bail', 'required', 'string', 'email', 'max:254'],
            'password' => ['bail', 'required', 'string', new PasswordBytes],
        ]);
        if (! Auth::attempt(['email' => strtolower($data['email']), 'password' => $data['password']])) {
            throw ValidationException::withMessages(['email' => 'The supplied credentials could not be verified.']);
        }
        $request->session()->regenerate();

        return redirect($request->user() instanceof User && $request->user()->hasMfa() ? '/account/mfa/challenge' : '/account');
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($request->user() instanceof User) {
            app(SessionRegistry::class)->revokeCurrent($request->session(), $request->user());
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
