<?php

namespace App\Providers;

use App\Models\Post;
use App\Models\User;
use App\Policies\PostPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (config('cms.payments_enabled') || config('cms.restricted_publishing_enabled')) {
            throw new LogicException('Payment and restricted-publication adapters are not implemented in 00.03.00.');
        }
        if ($this->app->environment('production') && (config('app.debug') || ! config('session.secure'))) {
            throw new LogicException('Production requires debug off and secure session cookies.');
        }
        Gate::define('manage-content', fn (User $user): bool => $user->is_admin);
        Gate::policy(Post::class, PostPolicy::class);
        RateLimiter::for('login', fn (Request $request): array => [
            Limit::perMinute(5)->by('account:'.hash('sha256', strtolower((string) $request->input('email')))),
            Limit::perMinute(20)->by('ip:'.hash('sha256', (string) $request->ip())),
        ]);
        RateLimiter::for('signup', fn (Request $request): Limit => Limit::perHour(10)->by((string) $request->ip()));
        RateLimiter::for('reports', fn (Request $request): Limit => Limit::perMinute(3)->by((string) $request->ip()));
        RateLimiter::for('invoices', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->user()?->getAuthIdentifier()));
    }
}
