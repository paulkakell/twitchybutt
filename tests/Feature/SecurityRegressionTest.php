<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function captureLogs(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cms-log-test-');
        $channel = config('logging.channels.daily');
        $channel['driver'] = 'single';
        $channel['path'] = $path;
        config(['logging.channels.security_test' => $channel, 'logging.default' => 'security_test']);
        Log::purge('security_test');

        return $path;
    }

    private function member(): User
    {
        return User::query()->create(['name' => 'Member', 'email' => Str::uuid().'@example.test', 'password' => 'ExamplePassword123']);
    }

    public function test_unmatched_routes_and_liveness_keep_security_headers(): void
    {
        foreach (['/not-a-real-route', '/up'] as $path) {
            $response = $this->get($path);
            $response->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Referrer-Policy', 'no-referrer');
            self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            self::assertNotEmpty($response->headers->get('Content-Security-Policy'));
            self::assertTrue(Str::isUuid($response->headers->get('X-Request-ID')));
        }
    }

    public function test_client_cannot_choose_the_request_identifier(): void
    {
        $supplied = (string) Str::uuid();
        $first = $this->withHeader('X-Request-ID', $supplied)->get('/not-a-real-route');
        $second = $this->get('/not-a-real-route');
        self::assertNotSame($supplied, $first->headers->get('X-Request-ID'));
        self::assertNotSame($first->headers->get('X-Request-ID'), $second->headers->get('X-Request-ID'));
    }

    public function test_validation_and_checkout_errors_are_not_cacheable(): void
    {
        $this->postJson('/register', [])->assertUnprocessable()->assertHeader('X-Frame-Options', 'DENY');
        $response = $this->actingAs($this->member())->postJson('/checkout');
        $response->assertStatus(503)->assertHeader('X-Content-Type-Options', 'nosniff');
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_member_cannot_modify_or_read_operator_records(): void
    {
        $post = Post::query()->create(['title' => 'Private draft', 'body' => 'PRIVATE_DRAFT_SENTINEL', 'classification' => 'general', 'status' => 'draft', 'price_units' => 0]);
        $this->actingAs($this->member());
        foreach (['/studio', '/studio/reports', '/studio/posts/new', '/studio/posts/'.$post->id.'/edit'] as $path) {
            $this->get($path)->assertForbidden()->assertDontSee('PRIVATE_DRAFT_SENTINEL')->assertHeader('X-Frame-Options', 'DENY');
        }
        $this->put('/studio/posts/'.$post->id, ['status' => 'published', 'is_admin' => true])->assertForbidden();
        self::assertSame('draft', $post->fresh()->status);
        self::assertFalse(User::query()->firstOrFail()->is_admin);
    }

    public function test_unknown_messages_and_nested_sensitive_context_are_redacted(): void
    {
        $path = $this->captureLogs();
        try {
            Log::warning('email=SENSITIVE_EMAIL@example.test bearer=SECRET_TOKEN_SENTINEL', [
                'password' => 'PASSWORD_SENTINEL', 'body' => 'REPORT_BODY_SENTINEL',
                'nested' => ['private_key' => 'PRIVATE_KEY_SENTINEL'], 'actor_id' => 23,
                'request_id' => 'REQUEST_HEADER_SENTINEL', 'exception' => new RuntimeException('EXCEPTION_SENTINEL'),
            ]);
            $text = file_get_contents($path);
            foreach (['SENSITIVE_EMAIL', 'SECRET_TOKEN_SENTINEL', 'PASSWORD_SENTINEL', 'REPORT_BODY_SENTINEL', 'PRIVATE_KEY_SENTINEL', 'REQUEST_HEADER_SENTINEL', 'EXCEPTION_SENTINEL'] as $secret) {
                self::assertStringNotContainsString($secret, $text);
            }
            $record = json_decode(trim($text), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('cms.log.redacted', $record['message']);
            self::assertSame(23, $record['context']['actor_id']);
            self::assertSame('WARNING', $record['level_name']);
        } finally {
            Log::purge('security_test');
            unlink($path);
        }
    }

    public function test_approved_events_keep_safe_identifiers_only(): void
    {
        $path = $this->captureLogs();
        try {
            $uuid = (string) Str::uuid();
            Log::info('cms.invoice.quoted', ['invoice_id' => $uuid, 'actor_id' => 7, 'email' => 'PRIVATE_EMAIL_SENTINEL']);
            $record = json_decode(trim(file_get_contents($path)), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('cms.invoice.quoted', $record['message']);
            self::assertSame(['actor_id' => 7, 'invoice_id' => $uuid], $record['context']);
        } finally {
            Log::purge('security_test');
            unlink($path);
        }
    }

    public function test_spoofed_safe_context_values_are_not_retained(): void
    {
        $path = $this->captureLogs();
        try {
            Log::error('cms.exception', ['actor_id' => 'ACTOR_SECRET_SENTINEL', 'post_id' => ['BODY_SECRET_SENTINEL'], 'exception_type' => 'ANONYMOUS_PATH_SENTINEL']);
            $text = file_get_contents($path);
            self::assertStringNotContainsString('SENTINEL', $text);
            self::assertStringContainsString('Throwable', $text);
        } finally {
            Log::purge('security_test');
            unlink($path);
        }
    }

    public function test_actual_exception_path_suppresses_sensitive_payloads(): void
    {
        $path = $this->captureLogs();
        try {
            config(['app.debug' => false]);
            Route::get('/__security-test-error', function (): never {
                throw new RuntimeException('PASSWORD_AND_REPORT_SENTINEL');
            });
            $this->getJson('/__security-test-error?token=QUERY_SECRET_SENTINEL')->assertStatus(500)->assertDontSee('PASSWORD_AND_REPORT_SENTINEL')->assertHeader('X-Frame-Options', 'DENY');
            $text = file_get_contents($path);
            self::assertStringContainsString('cms.exception', $text);
            self::assertStringNotContainsString('SENTINEL', $text);
        } finally {
            Log::purge('security_test');
            unlink($path);
        }
    }

    public function test_report_body_stays_out_of_runtime_logs(): void
    {
        $path = $this->captureLogs();
        try {
            $this->post('/report', ['reference' => 'PRIVATE_REFERENCE_SENTINEL', 'category' => 'other', 'description' => 'PRIVATE_REPORT_SENTINEL'])->assertRedirect('/report');
            $text = file_get_contents($path);
            self::assertStringContainsString('cms.report.received', $text);
            self::assertStringNotContainsString('SENTINEL', $text);
        } finally {
            Log::purge('security_test');
            unlink($path);
        }
    }
}
