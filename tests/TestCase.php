<?php

namespace Tests;

use App\Models\User;
use App\Services\SessionRegistry;
use App\Services\Totp;

abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    protected function actingAsMfa(User $user): static
    {
        // Explicit test fixture: separate from actingAs and all MFA boundary tests.
        $user->forceFill(['mfa_secret' => app(Totp::class)->secret(), 'mfa_confirmed_at' => now()])->save();
        $this->actingAs($user);
        $this->withSession(['mfa_verified_version' => $user->auth_version, 'mfa_verified_user' => (string) $user->id]);

        return $this;
    }

    public function actingAs($user, $guard = null)
    {
        parent::actingAs($user, $guard);
        if ($user instanceof User && ($guard === null || $guard === 'web')) {
            // actingAs bypasses the Login event; simulate its session snapshot, not middleware bypass.
            $this->withSession([]);
            app(SessionRegistry::class)->start(app('session.store'), $user);
        }

        return $this;
    }
}
