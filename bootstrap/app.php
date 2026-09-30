<?php

use App\Http\Middleware\AccountOrigin;
use App\Http\Middleware\AccountSession;
use App\Http\Middleware\RequireMfa;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\StrictThrottle;
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
        $middleware->web(append: [AccountSession::class, RequireMfa::class]);
        $middleware->alias(['strict' => StrictThrottle::class]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/account');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', '_token', 'token', 'code', 'recovery_code', 'secret']);
        $exceptions->render(function (Throwable $exception, Request $request): ?Response {
            // A rejected configuration must not activate Laravel's debug error renderer.
            return app()->isBooted() ? null : new Response('Service unavailable.', 503, ['Content-Type' => 'text/plain; charset=UTF-8']);
        });
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            return SecurityHeaders::apply($request, $response);
        });
        $exceptions->report(function (Throwable $exception): bool {
            // Exclude messages, bindings, URLs and traces containing private content.
            try {
                if (Log::getFacadeRoot() !== null) {
                    Log::error('cms.exception', ['exception_type' => $exception::class]);
                } else {
                    error_log('{"event":"cms.bootstrap.failed"}');
                }
            } catch (Throwable) {
                error_log('{"event":"cms.logging.failed"}');
            }

            return false;
        });
    })->create();
