<?php

namespace Tests\Feature;

use App\Models\Entitlement;
use App\Models\Post;
use App\Models\User;
use App\Services\AccountSecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountSessionTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::query()->create(['name' => 'Test member', 'email' => Str::uuid().'@example.test', 'password' => 'ExamplePassword123']);
    }

    public function test_reset_revokes_session_before_reading_entitled_content(): void
    {
        $user = $this->member();
        $post = Post::query()->create(['title' => 'Private', 'body' => 'SESSION_TEST_PRIVATE_BODY', 'classification' => 'general', 'status' => 'published', 'price_units' => 100]);
        // Explicit fixture creation preserves the production model's mass-assignment guard.
        (new Entitlement)->forceFill(['user_id' => $user->id, 'post_id' => $post->id])->save();
        $this->actingAs($user)->get('/posts/'.$post->id)->assertOk()->assertSee('SESSION_TEST_PRIVATE_BODY');
        self::assertTrue(app(AccountSecurityService::class)->reset(['email' => $user->email, 'token' => Password::broker()->createToken($user), 'password' => 'NewExamplePassword456', 'password_confirmation' => 'NewExamplePassword456']));
        $this->get('/posts/'.$post->id)->assertRedirect('/login')->assertDontSee('SESSION_TEST_PRIVATE_BODY');
        $this->assertGuest();
    }

    public function test_missing_stamp_requires_fresh_login(): void
    {
        $this->actingAs($this->member())->withSession(['auth_version' => null])->getJson('/account')->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_wrong_user_stamp_is_rejected(): void
    {
        $this->actingAs($this->member())->withSession(['auth_user_id' => '999999'])->getJson('/account')->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_revoked_administrator_session_is_rejected(): void
    {
        $user = $this->member();
        $user->is_admin = true;
        $user->save();
        $this->actingAs($user);
        User::query()->whereKey($user->id)->increment('auth_version');
        $this->get('/studio')->assertRedirect('/login');
    }

    public function test_login_uses_current_version_after_reset(): void
    {
        $user = $this->member();
        self::assertTrue(app(AccountSecurityService::class)->reset(['email' => $user->email, 'token' => Password::broker()->createToken($user), 'password' => 'NewExamplePassword456', 'password_confirmation' => 'NewExamplePassword456']));
        $this->post('/login', ['email' => $user->email, 'password' => 'ExamplePassword123'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'NewExamplePassword456'])->assertRedirect('/account')->assertSessionHas('auth_version', 2)->assertSessionHas('auth_user_id', (string) $user->id);
        $this->get('/account')->assertOk();
    }
}
