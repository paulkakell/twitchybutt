<?php

namespace App\Providers;

use App\Http\Middleware\RequireMfa;
use App\Models\Post;
use App\Models\User;
use App\Policies\PostPolicy;
use App\Services\AccountSecurityService;
use App\Services\MediaProcessor;
use App\Services\SessionRegistry;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (PHP_INT_SIZE !== 8) {
            throw new LogicException('Exact amount calculations require 64-bit PHP.');
        }
        if (config('cms.payments_enabled') || config('cms.restricted_publishing_enabled')) {
            throw new LogicException('Payment and restricted-publication adapters are not implemented in this preview.');
        }
        if ($this->app->environment('production') && (config('app.debug') || ! config('session.secure') || config('session.driver') === 'array' || config('cache.default') === 'array')) {
            throw new LogicException('Production requires debug off, secure cookies and persistent sessions/rate limits.');
        }
        AccountSecurityService::validateConfiguration();
        SessionRegistry::validateConfiguration();
        MediaProcessor::validateConfiguration();
        Event::listen(Login::class, function (Login $event): void {
            $request = request();
            if ($event->guard === 'web' && $event->user instanceof User && $request->hasSession()) {
                // Stamp the authenticated snapshot, not a newer DB version after a concurrent reset.
                app(SessionRegistry::class)->start($request->session(), $event->user);
            }
        });
        Gate::define('manage-content', fn (User $user): bool => $user->is_admin === true && RequireMfa::satisfied(request(), $user));
        Gate::policy(Post::class, PostPolicy::class);
    }
}
