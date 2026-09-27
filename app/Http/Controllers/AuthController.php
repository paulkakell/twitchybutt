<?php

namespace App\Http\Controllers;

use App\Models\User;
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
        $request->merge(['email' => strtolower((string) $request->input('email'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', 'max:72', Password::min(12)->letters()->numbers()],
        ]);
        $user = User::query()->create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        Auth::login($user);
        $request->session()->regenerate();
        Log::info('cms.member.registered', ['actor_id' => $user->id]);

        return redirect('/account');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:254'], 'password' => ['required', 'string', 'max:72']]);
        if (! Auth::attempt(['email' => strtolower($data['email']), 'password' => $data['password']])) {
            throw ValidationException::withMessages(['email' => 'The supplied credentials could not be verified.']);
        }
        $request->session()->regenerate();

        return redirect('/account');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
