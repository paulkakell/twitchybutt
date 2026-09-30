<?php

namespace Tests\Feature;

use App\Jobs\SendAccountMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['account_security.mail_enabled' => true, 'app.url' => 'http://localhost', 'mail.default' => 'array', 'queue.connections.database.after_commit' => false]);
    }

    private function member(): User
    {
        return User::query()->create(['name' => 'Test member', 'email' => Str::uuid().'@example.test', 'password' => 'ExamplePassword123']);
    }

    private function credentials(User $user): array
    {
        return ['email' => $user->email, 'token' => Password::broker()->createToken($user), 'password' => 'NewExamplePassword456', 'password_confirmation' => 'NewExamplePassword456'];
    }

    public function test_disabled_mail_returns_unavailable_and_does_not_queue(): void
    {
        Queue::fake();
        config(['account_security.mail_enabled' => false]);
        $this->get('/forgot-password')->assertOk()->assertSee('Account email is unavailable');
        $this->postJson('/forgot-password', ['email' => 'member@example.test'])->assertStatus(503);
        $this->postJson('/reset-password')->assertStatus(503);
        Queue::assertNothingPushed();
    }

    public function test_recovery_response_does_not_reveal_account_existence(): void
    {
        Queue::fake();
        $user = $this->member();
        $known = $this->postJson('/forgot-password', ['email' => strtoupper($user->email)])->assertStatus(202);
        $unknown = $this->postJson('/forgot-password', ['email' => 'absent@example.test'])->assertStatus(202);
        self::assertSame($known->json(), $unknown->json());
        Queue::assertPushed(SendAccountMail::class, 2);
        Queue::assertPushed(SendAccountMail::class, fn ($job) => $job->email === $user->email);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_requests_are_throttled_without_account_lookup(): void
    {
        Queue::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/forgot-password', ['email' => 'absent@example.test'])->assertStatus(202);
        }
        $this->postJson('/forgot-password', ['email' => 'ABSENT@example.test'])->assertStatus(429);
        Queue::assertPushed(SendAccountMail::class, 5);
    }

    public function test_array_email_is_rejected_without_queueing(): void
    {
        Queue::fake();
        $this->postJson('/forgot-password', ['email' => ['invalid']])->assertUnprocessable();
        Queue::assertNothingPushed();
    }

    public function test_token_is_hashed_and_single_use(): void
    {
        $user = $this->member();
        $data = $this->credentials($user);
        $stored = (string) DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        self::assertNotSame($data['token'], $stored);
        self::assertTrue(Hash::check($data['token'], $stored));
        $this->post('/reset-password', $data)->assertRedirect('/login');
        self::assertTrue(Hash::check('NewExamplePassword456', $user->fresh()->password));
        self::assertSame(2, $user->fresh()->auth_version);
        self::assertFalse($user->fresh()->is_admin);
        self::assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertGuest();
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->postJson('/reset-password', $data)->assertUnprocessable()->assertJsonValidationErrors('token');
    }

    public function test_expired_token_does_not_change_password(): void
    {
        $user = $this->member();
        $data = $this->credentials($user);
        DB::table('password_reset_tokens')->where('email', $user->email)->update(['created_at' => now()->subMinutes(31)]);
        $this->postJson('/reset-password', $data)->assertUnprocessable()->assertJsonValidationErrors('token');
        self::assertTrue(Hash::check('ExamplePassword123', $user->fresh()->password));
        self::assertSame(1, $user->fresh()->auth_version);
    }

    public function test_wrong_account_and_token_have_same_error(): void
    {
        $user = $this->member();
        $data = $this->credentials($user);
        $wrong = $this->postJson('/reset-password', array_merge($data, ['token' => str_repeat('a', 64)]))->assertUnprocessable();
        $absent = $this->postJson('/reset-password', array_merge($data, ['email' => 'absent@example.test']))->assertUnprocessable();
        self::assertSame($wrong->json(), $absent->json());
        self::assertTrue(Hash::check('ExamplePassword123', $user->fresh()->password));
    }

    public function test_reset_rejects_malformed_or_weak_credentials(): void
    {
        $user = $this->member();
        $data = $this->credentials($user);
        foreach ([['email' => ['invalid']], ['token' => ['invalid']], ['password' => 'short'], ['password' => str_repeat('é', 36).'A1']] as $override) {
            $this->postJson('/reset-password', array_merge($data, $override))->assertUnprocessable();
        }
        self::assertTrue(Hash::check('ExamplePassword123', $user->fresh()->password));
    }

    public function test_reset_secrets_are_not_flashed_to_old_input(): void
    {
        $data = $this->credentials($this->member());
        $this->post('/reset-password', array_merge($data, ['password_confirmation' => 'mismatch']))->assertSessionHasErrors('password')->assertSessionMissing('_old_input.token')->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
    }

    public function test_reset_throttle_is_enforced(): void
    {
        $data = $this->credentials($this->member());
        $data['token'] = str_repeat('a', 64);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/reset-password', $data)->assertUnprocessable();
        }
        $this->postJson('/reset-password', $data)->assertStatus(429);
    }

    public function test_registration_cannot_set_security_attributes(): void
    {
        $this->post('/register', ['name' => 'Test member', 'email' => 'new@example.test', 'password' => 'ExamplePassword123', 'password_confirmation' => 'ExamplePassword123', 'auth_version' => 99, 'email_verified_at' => now()->toIso8601String()])->assertRedirect('/account')->assertSessionHas('auth_version', 1);
        $user = User::query()->where('email', 'new@example.test')->firstOrFail();
        self::assertNull($user->email_verified_at);
        $this->get('/account/security')->assertOk()->assertSee('Not verified');
    }

    public function test_recovery_migration_preserves_existing_accounts_on_rollback(): void
    {
        $user = $this->member();
        $migration = require database_path('migrations/2026_09_27_000002_add_account_security.php');
        $migration->down();
        self::assertFalse(Schema::hasColumn('users', 'auth_version'));
        self::assertFalse(Schema::hasTable('password_reset_tokens'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
        $migration->up();
        self::assertSame(1, $user->fresh()->auth_version);
        self::assertNull($user->fresh()->email_verified_at);
    }
}
