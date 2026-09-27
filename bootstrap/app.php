<?php

use App\Http\Middleware\AccountOrigin;
use App\Http\Middleware\AccountSession;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend([SecurityHeaders::class, AccountOrigin::class]);
        $middleware->web(append: [AccountSession::class]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/account');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', '_token', 'token']);
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            return SecurityHeaders::apply($request, $response);
        });
        $exceptions->report(function (Throwable $exception): bool {
            // Exclude messages, bindings, URLs and traces containing private content.
            Log::error('cms.exception', ['exception_type' => $exception::class]);

            return false;
        });
    })->create();
