<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireMfa
{
    public static function satisfied(Request $request, User $user): bool
    {
        return $request->hasSession() && $user->hasMfa()
            && $request->session()->get('mfa_verified_version') === $user->auth_version
            && $request->session()->get('mfa_verified_user') === (string) $user->id;
    }

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && $user->hasMfa() && ! self::satisfied($request, $user)
            && ! $request->routeIs('mfa.challenge', 'mfa.verify', 'logout', 'password.*')) {
            return $request->expectsJson() ? response()->json(['message' => 'MFA verification required.'], 403) : redirect('/account/mfa/challenge');
        }
        if ($user instanceof User && $user->is_admin && ! $user->hasMfa() && $request->is('studio', 'studio/*')) {
            return $request->expectsJson() ? response()->json(['message' => 'Administrator MFA enrollment required.'], 403) : redirect('/account/mfa');
        }

        return $next($request);
    }
}
