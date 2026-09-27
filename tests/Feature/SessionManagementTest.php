<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SessionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

final class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::query()->create(['name' => 'Session test', 'email' => Str::uuid().'@example.test', 'password' => 'ExamplePassword123']);
    }

    private function otherSession(User $user): string
    {
        $id = (string) Str::uuid();
        DB::table('account_sessions')->insert(['id' => $id, 'user_id' => $user->id, 'auth_version' => $user->auth_version, 'created_at' => now(), 'last_seen_at' => now()]);

        return $id;
    }

    public function test_login_registers_private_metadata_only(): void
    {
        $user = $this->member();
        $this->post('/login', ['email' => $user->email, 'password' => 'ExamplePassword123'])->assertRedirect('/account');
        $row = DB::table('account_sessions')->first();
        self::assertSame((string) $row->id, session('account_session_id'));
        self::assertSame($user->id, (int) $row->user_id);
        self::assertSame(['id', 'user_id', 'auth_version', 'created_at', 'last_seen_at', 'revoked_at'], array_keys((array) $row));
    }

    public function test_missing_or_forged_registry_is_rejected(): void
    {
        $user = $this->member();
        $this->actingAs($user)->withSession(['account_session_id' => null])->getJson('/account')->assertUnauthorized();
        $this->actingAs($user)->withSession(['account_session_id' => (string) Str::uuid()])->getJson('/account')->assertUnauthorized();
    }

    public function test_cross_account_registry_is_rejected(): void
    {
        $otherId = $this->otherSession($this->member());
        $this->actingAs($this->member())->withSession(['account_session_id' => $otherId])->getJson('/account')->assertUnauthorized();
    }

    public function test_idle_expiry_is_enforced_at_deadline(): void
    {
        $this->actingAs($this->member())->get('/account')->assertOk();
        $this->travel(30)->minutes();
        $this->getJson('/account')->assertUnauthorized();
    }

    public function test_absolute_deadline_survives_recent_activity(): void
    {
        $this->actingAs($this->member())->get('/account')->assertOk();
        $id = session('account_session_id');
        $this->travel(720)->minutes();
        DB::table('account_sessions')->where('id', $id)->update(['last_seen_at' => now()]);
        $this->getJson('/account')->assertUnauthorized();
    }

    public function test_active_request_refreshes_idle_deadline(): void
    {
        $this->actingAs($this->member())->get('/account')->assertOk();
        $this->travel(20)->minutes();
        $this->get('/account')->assertOk();
        $this->travel(20)->minutes();
        $this->get('/account')->assertOk();
    }

    public function test_list_is_owner_only_and_omits_stale_sessions(): void
    {
        $user = $this->member();
        $otherId = $this->otherSession($this->member());
        $revoked = $this->otherSession($user);
        DB::table('account_sessions')->where('id', $revoked)->update(['revoked_at' => now()]);
        $response = $this->actingAs($user)->get('/account/sessions')->assertOk()->assertSee('This session');
        $response->assertDontSee($otherId)->assertDontSee($revoked)->assertDontSee($user->email);
    }

    public function test_revocation_requires_password_and_cannot_cross_accounts(): void
    {
        $user = $this->member();
        $own = $this->otherSession($user);
        $other = $this->otherSession($this->member());
        $this->actingAs($user)->post('/account/sessions/'.$own.'/revoke', ['password' => 'WrongPassword123'])->assertSessionHasErrors('password');
        self::assertNull(DB::table('account_sessions')->where('id', $own)->value('revoked_at'));
        $this->post('/account/sessions/'.$other.'/revoke', ['password' => 'ExamplePassword123'])->assertNotFound();
        self::assertNull(DB::table('account_sessions')->where('id', $other)->value('revoked_at'));
    }

    public function test_revoking_other_session_keeps_current_session_valid(): void
    {
        $user = $this->member();
        $other = $this->otherSession($user);
        $this->actingAs($user)->post('/account/sessions/'.$other.'/revoke', ['password' => 'ExamplePassword123'])->assertRedirect('/account/sessions');
        self::assertNotNull(DB::table('account_sessions')->where('id', $other)->value('revoked_at'));
        $this->get('/account')->assertOk();
        $this->withSession(['account_session_id' => $other])->getJson('/account')->assertUnauthorized();
    }

    public function test_revoking_current_session_logs_out(): void
    {
        $this->actingAs($this->member())->get('/account');
        $id = session('account_session_id');
        $this->post('/account/sessions/'.$id.'/revoke', ['password' => 'ExamplePassword123'])->assertRedirect('/login');
        $this->assertGuest();
        self::assertNotNull(DB::table('account_sessions')->where('id', $id)->value('revoked_at'));
    }

    public function test_logout_marks_registry_revoked(): void
    {
        $this->actingAs($this->member())->get('/account');
        $id = session('account_session_id');
        $this->post('/logout')->assertRedirect('/');
        self::assertNotNull(DB::table('account_sessions')->where('id', $id)->value('revoked_at'));
    }

    public function test_unbounded_timeout_configuration_is_rejected(): void
    {
        config(['account_security.absolute_minutes' => 99999]);
        $this->expectException(LogicException::class);
        SessionRegistry::validateConfiguration();
    }

    public function test_new_migration_rollback_keeps_users_and_content_tables(): void
    {
        $user = $this->member();
        $migration = require database_path('migrations/2026_09_27_000003_add_mfa_and_sessions.php');
        $migration->down();
        self::assertFalse(Schema::hasTable('mfa_recovery_codes'));
        self::assertFalse(Schema::hasColumn('users', 'mfa_secret'));
        self::assertTrue(Schema::hasTable('posts'));
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $migration->up();
        self::assertFalse($user->fresh()->hasMfa());
    }

    public function test_revoke_all_rotates_generation_without_touching_another_user(): void
    {
        $user = $this->member();
        $otherUser = $this->member();
        $other = $this->otherSession($user);
        $foreign = $this->otherSession($otherUser);
        $this->actingAs($user)->postJson('/account/sessions/revoke-all', ['password' => 'wrong'])->assertUnprocessable();
        $this->post('/account/sessions/revoke-all', ['password' => 'ExamplePassword123'])->assertRedirect('/login');
        $this->assertGuest();
        self::assertSame(2, $user->fresh()->auth_version);
        self::assertNotNull(DB::table('account_sessions')->where('id', $other)->value('revoked_at'));
        self::assertNull(DB::table('account_sessions')->where('id', $foreign)->value('revoked_at'));
    }

    public function test_security_prune_preserves_current_sessions_and_confirmed_factors(): void
    {
        $user = $this->member();
        $current = $this->otherSession($user);
        $expired = $this->otherSession($user);
        DB::table('account_sessions')->where('id', $expired)->update(['created_at' => now()->subDays(8)]);
        $user->forceFill(['mfa_pending_secret' => str_repeat('A', 32), 'mfa_pending_expires_at' => now()->subMinute()])->save();
        $confirmed = $this->member();
        $confirmed->forceFill(['mfa_secret' => str_repeat('B', 32), 'mfa_confirmed_at' => now()])->save();
        $this->artisan('cms:security-prune')->assertSuccessful();
        $this->assertDatabaseHas('account_sessions', ['id' => $current]);
        $this->assertDatabaseMissing('account_sessions', ['id' => $expired]);
        self::assertNull($user->fresh()->mfa_pending_secret);
        self::assertTrue($confirmed->fresh()->hasMfa());
        self::assertSame(str_repeat('B', 32), $confirmed->fresh()->mfa_secret);
    }
}
