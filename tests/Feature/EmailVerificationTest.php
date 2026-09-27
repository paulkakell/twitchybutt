<?php

namespace Tests\Feature;

use App\Jobs\SendAccountMail;
use App\Models\User;
use App\Notifications\AccountLink;
use App\Services\AccountSecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
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

    public function test_resend_is_bound_to_current_user_and_throttled(): void
    {
        Queue::fake();
        $user = $this->member();
        $this->actingAs($user)->post('/email/verification-notification', ['email' => 'other@example.test'])->assertRedirect('/account/security');
        Queue::assertPushed(SendAccountMail::class, fn ($job) => $job->email === $user->email && $job->purpose === 'verify' && $job->generation === 1);
        $this->post('/email/verification-notification')->assertStatus(429);
    }

    public function test_verification_requires_login(): void
    {
        $url = app(AccountSecurityService::class)->verificationUrl($this->member());
        $this->get($url)->assertRedirect('/login');
        $this->postJson('/email/verification-notification')->assertUnauthorized();
    }

    public function test_signed_link_verifies_only_once_without_role_changes(): void
    {
        $user = $this->member();
        $url = app(AccountSecurityService::class)->verificationUrl($user);
        $this->actingAs($user)->get($url)->assertRedirect('/account/security');
        $verified = $user->fresh()->email_verified_at;
        self::assertNotNull($verified);
        $this->get($url)->assertRedirect('/account/security');
        self::assertTrue($verified->eq($user->fresh()->email_verified_at));
        self::assertFalse($user->fresh()->is_admin);
    }

    public function test_modified_signature_fails(): void
    {
        $user = $this->member();
        $this->actingAs($user)->get(app(AccountSecurityService::class)->verificationUrl($user).'x')->assertForbidden();
        self::assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_expired_link_fails(): void
    {
        $user = $this->member();
        $url = app(AccountSecurityService::class)->verificationUrl($user);
        $this->travel(61)->minutes();
        $this->actingAs($user)->get($url)->assertForbidden();
        self::assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_different_account_cannot_redeem_link(): void
    {
        $user = $this->member();
        $other = $this->member();
        $this->actingAs($other)->get(app(AccountSecurityService::class)->verificationUrl($user))->assertForbidden();
        self::assertFalse($user->fresh()->hasVerifiedEmail());
        self::assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_changed_email_invalidates_old_link(): void
    {
        $user = $this->member();
        $url = app(AccountSecurityService::class)->verificationUrl($user);
        $user->email = 'changed@example.test';
        $user->save();
        $this->actingAs($user)->get($url)->assertForbidden();
    }

    public function test_changed_generation_invalidates_old_link(): void
    {
        $user = $this->member();
        $url = app(AccountSecurityService::class)->verificationUrl($user);
        $user->auth_version = 2;
        $user->save();
        $this->actingAs($user)->get($url)->assertForbidden();
    }

    public function test_unknown_host_is_rejected(): void
    {
        // The absolute test URL sets the actual request host; the configured origin stays unchanged.
        $this->get('http://other.example/forgot-password')->assertStatus(400);
        self::assertStringStartsWith('http://localhost/email/verify/', app(AccountSecurityService::class)->verificationUrl($this->member()));
    }

    public function test_database_queue_encrypts_recipient_and_executes_delivery(): void
    {
        Notification::fake();
        $user = $this->member();
        $this->postJson('/forgot-password', ['email' => $user->email])->assertStatus(202);
        $payload = (string) DB::table('jobs')->value('payload');
        self::assertNotEmpty($payload);
        self::assertStringNotContainsString($user->email, $payload);
        $job = Queue::connection('database')->pop('account-mail');
        self::assertNotNull($job);
        $job->fire();
        Notification::assertSentTo($user, AccountLink::class, fn ($notice) => $notice->purpose === 'reset' && str_starts_with($notice->url, 'http://localhost/reset-password?token=') && ! str_contains($notice->url, $user->email));
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_absent_account_and_old_jobs_do_not_send(): void
    {
        Notification::fake();
        $service = app(AccountSecurityService::class);
        (new SendAccountMail('absent@example.test', 'reset', now()->getTimestamp()))->handle($service);
        $user = $this->member();
        (new SendAccountMail($user->email, 'reset', now()->subMinutes(16)->getTimestamp()))->handle($service);
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_disabled_worker_and_old_generation_do_not_send(): void
    {
        Notification::fake();
        $service = app(AccountSecurityService::class);
        $user = $this->member();
        (new SendAccountMail($user->email, 'verify', now()->getTimestamp(), 999))->handle($service);
        config(['account_security.mail_enabled' => false]);
        (new SendAccountMail($user->email, 'reset', now()->getTimestamp()))->handle($service);
        Notification::assertNothingSent();
    }

    public function test_job_requested_before_reset_cannot_reissue_token(): void
    {
        Notification::fake();
        $user = $this->member();
        $job = new SendAccountMail($user->email, 'reset', now()->getTimestamp());
        self::assertTrue(app(AccountSecurityService::class)->reset(['email' => $user->email, 'token' => Password::broker()->createToken($user), 'password' => 'NewExamplePassword456', 'password_confirmation' => 'NewExamplePassword456']));
        $job->handle(app(AccountSecurityService::class));
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_enqueue_failure_returns_generic_unavailable(): void
    {
        Bus::shouldReceive('dispatch')->twice()->andThrow(new RuntimeException('Synthetic transport failure'));
        $user = $this->member();
        $known = $this->postJson('/forgot-password', ['email' => $user->email])->assertStatus(503)->assertDontSee('Synthetic transport failure');
        $unknown = $this->postJson('/forgot-password', ['email' => 'absent@example.test'])->assertStatus(503);
        self::assertSame($known->json(), $unknown->json());
    }

    public function test_failed_transport_rolls_back_token(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Synthetic SMTP failure'));
        $user = $this->member();
        try {
            (new SendAccountMail($user->email, 'reset', now()->getTimestamp()))->handle(app(AccountSecurityService::class));
            self::fail('Expected SMTP failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Synthetic SMTP failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_mail_template_includes_link_and_expiry(): void
    {
        $notice = new AccountLink('reset', 'http://localhost/reset-password?token='.str_repeat('b', 64));
        $mail = $notice->toMail($this->member());
        self::assertSame('Reset your site password', $mail->subject);
        self::assertSame($notice->url, $mail->actionUrl);
        self::assertStringContainsString('30 minutes', implode(' ', $mail->outroLines));
        self::assertStringContainsString('Reset password', (string) $mail->render());
    }

    public function test_http_nonlocal_link_origin_is_rejected(): void
    {
        config(['app.url' => 'http://creator.example']);
        $this->expectException(LogicException::class);
        AccountSecurityService::origin();
    }

    public function test_log_mailer_is_rejected_outside_tests(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.url' => 'https://creator.example', 'mail.default' => 'log']);
        $this->expectException(LogicException::class);
        AccountSecurityService::validateConfiguration();
    }
}
