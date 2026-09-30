<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\AccountSecurityService;
use App\Services\MfaService;
use App\Services\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class MfaTest extends TestCase
{
    use RefreshDatabase;

    private function member(bool $admin = false): User
    {
        $user = User::query()->create(['name' => 'MFA test', 'email' => Str::uuid().'@example.test', 'password' => 'ExamplePassword123']);
        $user->is_admin = $admin;
        $user->save();

        return $user;
    }

    private function code(User $user, bool $pending = false): string
    {
        return app(Totp::class)->code((string) ($pending ? $user->mfa_pending_secret : $user->mfa_secret), intdiv(now()->getTimestamp(), 30));
    }

    /** @return array{User, list<string>} */
    private function enrolled(bool $admin = false): array
    {
        $user = $this->member($admin);
        app(MfaService::class)->begin($user);
        $codes = app(MfaService::class)->confirm($user, $this->code($user->fresh(), true));
        $this->travel(31)->seconds();

        return [$user->fresh(), $codes];
    }

    public function test_administrator_must_enroll_before_studio_or_private_drafts(): void
    {
        $user = $this->member(true);
        $post = Post::query()->create(['title' => 'Secret', 'body' => 'PRIVATE_MFA_SENTINEL', 'classification' => 'restricted', 'status' => 'draft', 'price_units' => 0]);
        $this->actingAs($user)->get('/studio')->assertRedirect('/account/mfa');
        $this->getJson('/studio')->assertForbidden();
        $this->get('/posts/'.$post->id)->assertNotFound()->assertDontSee('PRIVATE_MFA_SENTINEL');
        $this->get('/account/mfa')->assertOk();
    }

    public function test_enrollment_requires_current_password_and_encrypts_pending_key(): void
    {
        $user = $this->member();
        $this->actingAs($user)->post('/account/mfa/enroll', ['password' => 'WrongPassword123'])->assertSessionHasErrors('password');
        self::assertNull($user->fresh()->mfa_pending_secret);
        $response = $this->post('/account/mfa/enroll', ['password' => 'ExamplePassword123'])->assertOk();
        $secret = $user->fresh()->mfa_pending_secret;
        self::assertNotNull($secret);
        self::assertNotSame($secret, DB::table('users')->where('id', $user->id)->value('mfa_pending_secret'));
        self::assertArrayNotHasKey('mfa_pending_secret', $user->fresh()->toArray());
        $response->assertSee($secret)->assertHeader('Referrer-Policy', 'no-referrer');
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_confirmation_issues_hashed_codes_once_and_revokes_other_sessions(): void
    {
        $user = $this->member(true);
        $this->actingAs($user)->get('/account');
        $prior = session('account_session_id');
        $this->post('/account/mfa/enroll', ['password' => 'ExamplePassword123'])->assertOk();
        $secret = $user->fresh()->mfa_pending_secret;
        $response = $this->post('/account/mfa/confirm', ['password' => 'ExamplePassword123', 'code' => $this->code($user->fresh(), true)])->assertOk();
        preg_match_all('/[0-9a-f]{8}(?:-[0-9a-f]{8}){3}/', $response->getContent(), $matches);
        self::assertCount(10, array_unique($matches[0]));
        self::assertTrue($user->fresh()->hasMfa());
        self::assertSame(2, $user->fresh()->auth_version);
        self::assertNull($user->fresh()->mfa_pending_secret);
        self::assertNotSame($secret, DB::table('users')->where('id', $user->id)->value('mfa_secret'));
        self::assertNotNull(DB::table('account_sessions')->where('id', $prior)->value('revoked_at'));
        $this->assertDatabaseCount('mfa_recovery_codes', 10);
        foreach ($matches[0] as $code) {
            $this->assertDatabaseHas('mfa_recovery_codes', ['user_id' => $user->id, 'code_hash' => hash('sha256', str_replace('-', '', $code))]);
            self::assertStringNotContainsString($code, json_encode(session()->all()));
            $this->get('/account/mfa')->assertDontSee($code);
        }
        $this->get('/studio')->assertOk();
    }

    public function test_expired_enrollment_cannot_be_confirmed(): void
    {
        $user = $this->member();
        $this->actingAs($user)->post('/account/mfa/enroll', ['password' => 'ExamplePassword123'])->assertOk();
        $this->travel(11)->minutes();
        $this->post('/account/mfa/confirm', ['password' => 'ExamplePassword123', 'code' => $this->code($user->fresh(), true)])->assertSessionHasErrors('code');
        self::assertFalse($user->fresh()->hasMfa());
        $this->assertDatabaseCount('mfa_recovery_codes', 0);
    }

    public function test_password_only_session_cannot_read_account_or_operator_pages(): void
    {
        [$user] = $this->enrolled(true);
        $this->post('/login', ['email' => $user->email, 'password' => 'ExamplePassword123'])->assertRedirect('/account/mfa/challenge');
        foreach (['/account', '/studio', '/account/mfa', '/account/sessions'] as $path) {
            $this->get($path)->assertRedirect('/account/mfa/challenge');
        }
        $this->get('/account/mfa/challenge')->assertOk();
        $this->post('/account/mfa/challenge', ['code' => $this->code($user)])->assertRedirect('/account');
        $this->get('/studio')->assertOk();
    }

    public function test_totp_replay_is_rejected_across_sessions(): void
    {
        [$user] = $this->enrolled();
        $code = $this->code($user);
        $this->actingAs($user)->post('/account/mfa/challenge', ['code' => $code])->assertRedirect('/account');
        $this->actingAs($user->fresh())->post('/account/mfa/challenge', ['code' => $code])->assertSessionHasErrors('code');
        $this->get('/account')->assertRedirect('/account/mfa/challenge');
        $this->travel(31)->seconds();
        $this->post('/account/mfa/challenge', ['code' => $this->code($user)])->assertRedirect('/account');
    }

    public function test_recovery_code_is_single_use_and_bound_to_owner(): void
    {
        [$owner, $codes] = $this->enrolled();
        [$other] = $this->enrolled();
        $this->actingAs($other)->post('/account/mfa/challenge', ['code' => $codes[0], 'recovery' => 1])->assertSessionHasErrors('code');
        $this->actingAs($owner)->post('/account/mfa/challenge', ['code' => $codes[0], 'recovery' => 1])->assertRedirect('/account');
        self::assertSame(9, DB::table('mfa_recovery_codes')->where('user_id', $owner->id)->count());
        $this->actingAs($owner)->post('/account/mfa/challenge', ['code' => $codes[0], 'recovery' => 1])->assertSessionHasErrors('code');
        $this->getJson('/account')->assertForbidden();
    }

    public function test_recovery_rotation_requires_password_and_new_totp(): void
    {
        [$user, $oldCodes] = $this->enrolled();
        $this->actingAs($user)->post('/account/mfa/challenge', ['code' => $oldCodes[0], 'recovery' => 1])->assertRedirect('/account');
        $this->post('/account/mfa/recovery-codes', ['code' => $this->code($user), 'password' => 'WrongPassword123'])->assertSessionHasErrors('password');
        $this->post('/account/mfa/recovery-codes', ['code' => $this->code($user), 'password' => 'ExamplePassword123'])->assertOk();
        $this->assertDatabaseMissing('mfa_recovery_codes', ['code_hash' => hash('sha256', str_replace('-', '', $oldCodes[1]))]);
        self::assertSame(10, DB::table('mfa_recovery_codes')->where('user_id', $user->id)->count());
    }

    public function test_password_reset_does_not_remove_mfa_or_recovery_codes(): void
    {
        [$user] = $this->enrolled(true);
        $secret = $user->mfa_secret;
        self::assertTrue(app(AccountSecurityService::class)->reset(['email' => $user->email, 'token' => Password::broker()->createToken($user), 'password' => 'NewExamplePassword456', 'password_confirmation' => 'NewExamplePassword456']));
        self::assertSame($secret, $user->fresh()->mfa_secret);
        self::assertTrue($user->fresh()->hasMfa());
        $this->assertDatabaseCount('mfa_recovery_codes', 10);
        $this->post('/login', ['email' => $user->email, 'password' => 'NewExamplePassword456'])->assertRedirect('/account/mfa/challenge');
        $this->get('/studio')->assertRedirect('/account/mfa/challenge');
    }

    public function test_challenge_is_throttled_and_does_not_flash_codes(): void
    {
        [$user] = $this->enrolled();
        $this->actingAs($user);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/account/mfa/challenge', ['code' => 'invalid'])->assertSessionHasErrors('code')->assertSessionMissing('_old_input.code');
        }
        $this->post('/account/mfa/challenge', ['code' => 'invalid'])->assertStatus(429);
    }

    public function test_malformed_codes_are_rejected(): void
    {
        [$user] = $this->enrolled();
        $this->actingAs($user)->postJson('/account/mfa/challenge', ['code' => ['bad']])->assertUnprocessable();
        $this->postJson('/account/mfa/challenge', ['code' => str_repeat('a', 36)])->assertUnprocessable();
        $this->postJson('/account/mfa/challenge', ['code' => '123456', 'recovery' => ['bad']])->assertUnprocessable();
    }

    public function test_client_cannot_supply_mfa_state_on_registration(): void
    {
        $this->post('/register', ['name' => 'New', 'email' => 'new@example.test', 'password' => 'ExamplePassword123', 'password_confirmation' => 'ExamplePassword123', 'mfa_secret' => 'ATTACKER_CONTROLLED', 'mfa_confirmed_at' => now()->toIso8601String(), 'mfa_verified_version' => 1])->assertRedirect('/account');
        $user = User::query()->firstOrFail();
        self::assertFalse($user->hasMfa());
        self::assertNull(session('mfa_verified_version'));
    }

    public function test_stale_generation_cannot_consume_mfa_proof(): void
    {
        [$user, $codes] = $this->enrolled();
        User::query()->whereKey($user->id)->increment('auth_version');
        try {
            app(MfaService::class)->challenge($user, $codes[0], true);
            self::fail('Stale generation was accepted.');
        } catch (HttpException $exception) {
            self::assertSame(401, $exception->getStatusCode());
        }
        self::assertSame(10, DB::table('mfa_recovery_codes')->where('user_id', $user->id)->count());
    }

    public function test_mfa_is_not_cleared_by_logout_or_relogin(): void
    {
        [$user, $codes] = $this->enrolled();
        $this->actingAs($user)->post('/account/mfa/challenge', ['code' => $codes[0], 'recovery' => 1])->assertRedirect('/account');
        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', ['email' => $user->email, 'password' => 'ExamplePassword123'])->assertRedirect('/account/mfa/challenge')->assertSessionMissing('mfa_verified_version');
        $this->get('/account')->assertRedirect('/account/mfa/challenge');
    }

    public function test_missing_key_does_not_disable_confirmed_mfa(): void
    {
        [$user] = $this->enrolled();
        DB::table('users')->where('id', $user->id)->update(['mfa_secret' => null]);
        $this->actingAs($user->fresh())->getJson('/account')->assertForbidden();
        $this->get('/account')->assertRedirect('/account/mfa/challenge');
        self::assertTrue($user->fresh()->hasMfa());
    }

    public function test_recent_recovery_sign_in_can_replace_lost_authenticator_without_disabling_mfa(): void
    {
        [$user, $oldCodes] = $this->enrolled();
        $oldSecret = $user->mfa_secret;
        $this->actingAs($user)->post('/account/mfa/challenge', ['code' => $oldCodes[0], 'recovery' => 1])->assertRedirect('/account');
        $this->post('/account/mfa/replace', ['password' => 'ExamplePassword123'])->assertOk();
        self::assertSame($oldSecret, $user->fresh()->mfa_secret);
        self::assertTrue($user->fresh()->hasMfa());
        $newSecret = $user->fresh()->mfa_pending_secret;
        $this->post('/account/mfa/confirm', ['password' => 'ExamplePassword123', 'code' => $this->code($user->fresh(), true)])->assertOk();
        self::assertSame($newSecret, $user->fresh()->mfa_secret);
        self::assertNotSame($oldSecret, $user->fresh()->mfa_secret);
        $this->assertDatabaseMissing('mfa_recovery_codes', ['code_hash' => hash('sha256', str_replace('-', '', $oldCodes[1]))]);
        $this->assertDatabaseCount('mfa_recovery_codes', 10);
        self::assertSame(3, $user->fresh()->auth_version);
    }

    public function test_old_mfa_session_cannot_replace_factor_with_password_alone(): void
    {
        [$user, $codes] = $this->enrolled();
        $this->actingAs($user)->post('/account/mfa/challenge', ['code' => $codes[0], 'recovery' => 1]);
        $this->travel(5)->minutes();
        $this->post('/account/mfa/replace', ['password' => 'ExamplePassword123'])->assertForbidden();
        self::assertNull($user->fresh()->mfa_pending_secret);
    }

    public function test_pending_enrollment_is_bound_to_authentication_generation(): void
    {
        $user = $this->member();
        app(MfaService::class)->begin($user);
        $code = $this->code($user->fresh(), true);
        User::query()->whereKey($user->id)->increment('auth_version');
        $this->actingAs($user->fresh())->post('/account/mfa/confirm', ['password' => 'ExamplePassword123', 'code' => $code])->assertSessionHasErrors('code');
        self::assertFalse($user->fresh()->hasMfa());
    }

    public function test_abandoned_replacement_keeps_existing_factor_and_prunes_only_pending_key(): void
    {
        [$user, $codes] = $this->enrolled();
        $oldSecret = $user->mfa_secret;
        $this->actingAs($user)->post('/account/mfa/challenge', ['code' => $codes[0], 'recovery' => 1]);
        $this->post('/account/mfa/replace', ['password' => 'ExamplePassword123'])->assertOk();
        $this->travel(11)->minutes();
        $this->artisan('cms:security-prune')->assertSuccessful();
        self::assertSame($oldSecret, $user->fresh()->mfa_secret);
        self::assertTrue($user->fresh()->hasMfa());
        self::assertNull($user->fresh()->mfa_pending_secret);
    }
}
