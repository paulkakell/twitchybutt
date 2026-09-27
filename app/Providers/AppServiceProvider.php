<?php

namespace App\Providers;

use App\Models\Post;
use App\Models\User;
use App\Policies\PostPolicy;
use App\Services\AccountSecurityService;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Event::listen(Login::class, function (Login $event): void {
            $request = request();
            if ($event->guard === 'web' && $event->user instanceof User && $request->hasSession()) {
                // Stamp the authenticated snapshot, not a newer DB version after a concurrent reset.
                $request->session()->put(['auth_user_id' => (string) $event->user->id, 'auth_version' => $event->user->auth_version]);
            }
        });
        Gate::define('manage-content', fn (User $user): bool => $user->is_admin === true);
        Gate::policy(Post::class, PostPolicy::class);
        RateLimiter::for('login', function (Request $request): array {
            $input = $request->input('email');
            $email = is_string($input) ? strtolower($input) : 'invalid-input';

            return [
                Limit::perMinute(5)->by('account:'.hash('sha256', $email)),
                Limit::perMinute(20)->by('ip:'.hash('sha256', (string) $request->ip())),
            ];
        });
        RateLimiter::for('signup', fn (Request $request): Limit => Limit::perHour(10)->by((string) $request->ip()));
        RateLimiter::for('reports', fn (Request $request): Limit => Limit::perMinute(3)->by((string) $request->ip()));
        RateLimiter::for('invoices', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->user()?->getAuthIdentifier()));
        RateLimiter::for('recovery', function (Request $request): array {
            $input = $request->input('email');
            $email = is_string($input) ? strtolower($input) : 'invalid-input';

            return [Limit::perHour(5)->by('recovery:'.hash_hmac('sha256', $email, (string) config('app.key'))), Limit::perHour(20)->by('recovery-ip:'.(string) $request->ip())];
        });
        RateLimiter::for('reset', fn (Request $request): Limit => Limit::perMinute(5)->by('reset-ip:'.(string) $request->ip()));
        RateLimiter::for('verification', fn (Request $request): array => [Limit::perMinute(1)->by('verify:'.(string) $request->user()?->getAuthIdentifier()), Limit::perHour(6)->by('verify-hour:'.(string) $request->user()?->getAuthIdentifier())]);
    }
}
