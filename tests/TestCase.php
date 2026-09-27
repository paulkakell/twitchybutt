<?php

namespace Tests;

use App\Models\User;

abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    public function actingAs($user, $guard = null)
    {
        parent::actingAs($user, $guard);
        if ($user instanceof User && ($guard === null || $guard === 'web')) {
            // actingAs bypasses the Login event; simulate its session snapshot, not middleware bypass.
            $this->withSession(['auth_user_id' => (string) $user->id, 'auth_version' => $user->auth_version]);
        }

        return $this;
    }
}
