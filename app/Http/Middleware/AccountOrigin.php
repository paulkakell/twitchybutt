<?php

namespace App\Http\Middleware;

use App\Services\AccountSecurityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AccountOrigin
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('account_security.mail_enabled')) {
            $expected = parse_url(AccountSecurityService::origin(), PHP_URL_HOST);
            abort_unless($request->getHost() === $expected, 400, 'Unrecognized site host.');
        }

        return $next($request);
    }
}
