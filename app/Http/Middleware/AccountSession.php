<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class AccountSession
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User) {
            $current = User::query()->find($user->id);
            if ($current === null || $request->session()->get('auth_user_id') !== (string) $current->id || $request->session()->get('auth_version') !== $current->auth_version) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return $request->expectsJson() ? response()->json(['message' => 'Please sign in again.'], 401) : redirect('/login')->with('status', 'Please sign in again.');
            }
            Auth::guard('web')->setUser($current);
        }

        return $next($request);
    }
}
