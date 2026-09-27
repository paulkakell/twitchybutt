<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_array_email_cannot_crash_registration_or_login(): void
    {
        $data = ['name' => 'Member', 'email' => ['invalid'], 'password' => 'ExamplePassword123', 'password_confirmation' => 'ExamplePassword123'];
        $this->postJson('/register', $data)->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/login', $data)->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_byte_limit_rejects_long_unicode_input(): void
    {
        $password = str_repeat('é', 36).'A1';
        $this->postJson('/register', ['name' => 'Member', 'email' => 'member@example.test', 'password' => $password, 'password_confirmation' => $password])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_null_byte_password_is_rejected_before_hashing(): void
    {
        $password = "Example\0Password123";
        $this->postJson('/register', ['name' => 'Member', 'email' => 'member@example.test', 'password' => $password, 'password_confirmation' => $password])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/login', ['email' => 'member@example.test', 'password' => $password])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_local_admin_command_creates_a_hashed_admin(): void
    {
        $this->artisan('cms:admin', ['--email' => 'creator@example.test'])
            ->expectsQuestion('Password', 'ExamplePassword123')
            ->expectsQuestion('Confirm password', 'ExamplePassword123')
            ->assertExitCode(0);
        $admin = User::query()->firstOrFail();
        self::assertTrue($admin->is_admin);
        self::assertTrue(Hash::check('ExamplePassword123', $admin->password));
    }

    public function test_local_admin_command_does_not_promote_existing_member(): void
    {
        User::query()->create(['name' => 'Member', 'email' => 'member@example.test', 'password' => 'ExamplePassword123']);
        $this->artisan('cms:admin', ['--email' => 'member@example.test'])
            ->expectsQuestion('Password', 'ExamplePassword123')
            ->expectsQuestion('Confirm password', 'ExamplePassword123')
            ->assertExitCode(1);
        self::assertFalse(User::query()->firstOrFail()->is_admin);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_production_rejects_ephemeral_rate_limits(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => false, 'session.secure' => true, 'session.driver' => 'file', 'cache.default' => 'array']);
        $this->expectException(LogicException::class);
        (new AppServiceProvider($this->app))->boot();
    }
}
