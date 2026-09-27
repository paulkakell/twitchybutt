<?php

namespace App\Http\Middleware;

use App\Services\AttemptBudget;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class StrictThrottle
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $mode): Response
    {
        $ip = (string) $request->ip();
        $input = $request->input('email');
        $email = is_string($input) ? strtolower(substr($input, 0, 254)) : 'invalid-input';
        $user = (string) $request->user()?->getAuthIdentifier();
        // No account-existence lookup or raw identity stored in the budget table.
        $scopes = match ($mode) {
            'login' => [["login-ip:$ip", 20, 60], ["login-account:$email", 5, 60]],
            'mfa' => [["mfa-ip:$ip", 20, 60], ["mfa-user:$user", 5, 60]],
            'signup' => [["signup-ip:$ip", 10, 3600]],
            'reports' => [["reports-ip:$ip", 3, 60]],
            'invoices' => [["invoice-ip:$ip", 30, 60], ["invoice-user:$user", 10, 60]],
            'recovery' => [["recovery-ip:$ip", 20, 3600], ["recovery-account:$email", 5, 3600]],
            'reset' => [["reset-ip:$ip", 5, 60]],
            'verification' => [["verify-hour:$user", 6, 3600], ["verify-minute:$user", 1, 60]],
            'verify-link' => [["verify-link-ip:$ip", 20, 60], ["verify-link-user:$user", 10, 60]],
            default => throw new LogicException('Unknown attempt budget.'),
        };
        if (! app(AttemptBudget::class)->consume($scopes)) {
            return response()->json(['message' => 'Too many attempts. Please try again later.'], 429, ['Retry-After' => (string) max(array_column($scopes, 2))]);
        }

        return $next($request);
    }
}
