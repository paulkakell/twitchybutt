<?php

namespace Tests\Feature;

use App\Models\Entitlement;
use App\Models\Invoice;
use App\Models\Post;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class CmsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(bool $admin = false): User
    {
        $user = new User(['name' => 'Reader', 'email' => Str::uuid().'@example.test', 'password' => 'ExamplePassword123']);
        $user->is_admin = $admin;
        $user->save();

        return $user;
    }

    private function makePost(array $attributes = []): Post
    {
        return Post::query()->create(array_merge(['title' => 'A new chapter', 'body' => 'PRIVATE_BODY_MARKER', 'classification' => 'general', 'status' => 'published', 'price_units' => 20000000], $attributes));
    }

    private function postData(array $overrides = []): array
    {
        return array_merge(['title' => 'A new chapter', 'body' => 'A plain text article.', 'classification' => 'general', 'status' => 'published', 'price' => '20'], $overrides);
    }

    public function test_home_page_and_security_headers(): void
    {
        $response = $this->get('/');
        $response->assertOk()->assertSee('Independent work.')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Referrer-Policy', 'no-referrer');
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_signup_never_accepts_admin_role_and_hashes_password(): void
    {
        $this->post('/register', ['name' => 'Member', 'email' => 'READER@example.test', 'password' => 'ExamplePassword123', 'password_confirmation' => 'ExamplePassword123', 'is_admin' => true])->assertRedirect('/account');
        $user = User::query()->firstOrFail();
        self::assertFalse($user->is_admin);
        self::assertSame('reader@example.test', $user->email);
        self::assertTrue(Hash::check('ExamplePassword123', $user->password));
        self::assertArrayNotHasKey('password', $user->toArray());
    }

    public function test_weak_password_is_rejected(): void
    {
        $this->post('/register', ['name' => 'Member', 'email' => 'reader@example.test', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_and_logout(): void
    {
        $user = $this->makeUser();
        $this->post('/login', ['email' => $user->email, 'password' => 'ExamplePassword123'])->assertRedirect('/account');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_bad_login_is_generic_and_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'absent@example.test', 'password' => 'IncorrectPassword123'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'absent@example.test', 'password' => 'IncorrectPassword123'])->assertStatus(429);
    }

    public function test_member_cannot_use_studio(): void
    {
        $this->actingAs($this->makeUser())->get('/studio')->assertForbidden();
        $this->post('/studio/posts', $this->postData())->assertForbidden();
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_admin_can_create_edit_and_unpublish_a_post(): void
    {
        $this->actingAsMfa($this->makeUser(true))->post('/studio/posts', $this->postData())->assertRedirect('/studio');
        $post = Post::query()->firstOrFail();
        $this->get('/studio/posts/'.$post->id.'/edit')->assertOk();
        $this->put('/studio/posts/'.$post->id, $this->postData(['title' => 'Revised', 'status' => 'draft']))->assertRedirect('/studio');
        self::assertSame('draft', $post->fresh()->status);
        $this->get('/studio')->assertOk()->assertSee('Revised');
    }

    public function test_restricted_and_unclassified_publication_is_rejected(): void
    {
        $this->actingAsMfa($this->makeUser(true));
        foreach (['restricted', 'unclassified'] as $classification) {
            $this->post('/studio/posts', $this->postData(['classification' => $classification]))->assertSessionHasErrors('classification');
        }
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_restricted_draft_is_saved_but_not_public(): void
    {
        $this->actingAsMfa($this->makeUser(true))->post('/studio/posts', $this->postData(['classification' => 'restricted', 'status' => 'draft']))->assertRedirect('/studio');
        $post = Post::query()->firstOrFail();
        $this->get('/posts/'.$post->id)->assertOk()->assertSee('A plain text article.');
        $this->post('/logout');
        $this->get('/posts/'.$post->id)->assertNotFound();
        $this->get('/')->assertDontSee('A new chapter');
    }

    public function test_forged_restricted_published_record_is_not_exposed(): void
    {
        $post = $this->makePost(['classification' => 'restricted', 'price_units' => 0, 'title' => 'HIDDEN_TITLE']);
        $this->get('/')->assertDontSee('HIDDEN_TITLE');
        $this->get('/posts/'.$post->id)->assertNotFound()->assertDontSee('PRIVATE_BODY_MARKER');
    }

    public function test_paid_body_is_not_in_catalog_or_paywall_html(): void
    {
        $post = $this->makePost();
        $this->get('/')->assertSee('A new chapter')->assertDontSee('PRIVATE_BODY_MARKER');
        $this->get('/posts/'.$post->id)->assertOk()->assertSee('This post is locked.')->assertDontSee('PRIVATE_BODY_MARKER');
    }

    public function test_free_body_is_readable_and_html_is_escaped(): void
    {
        $post = $this->makePost(['price_units' => 0, 'body' => '<script>alert(1)</script>']);
        $this->get('/posts/'.$post->id)->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>', false);
    }

    public function test_live_entitlement_allows_access_while_payments_disabled(): void
    {
        $post = $this->makePost();
        $user = $this->makeUser();
        (new Entitlement)->forceFill(['user_id' => $user->id, 'post_id' => $post->id, 'expires_at' => now()->addDay()])->save();
        $this->actingAs($user)->get('/posts/'.$post->id)->assertSee('PRIVATE_BODY_MARKER');
        self::assertFalse(config('cms.payments_enabled'));
    }

    public function test_expired_and_revoked_entitlements_do_not_unlock(): void
    {
        $post = $this->makePost();
        $user = $this->makeUser();
        $entitlement = new Entitlement;
        $entitlement->forceFill(['user_id' => $user->id, 'post_id' => $post->id, 'expires_at' => now()->subSecond()])->save();
        $this->actingAs($user)->get('/posts/'.$post->id)->assertDontSee('PRIVATE_BODY_MARKER');
        $entitlement->forceFill(['expires_at' => now()->addDay(), 'revoked_at' => now()])->save();
        $this->get('/posts/'.$post->id)->assertDontSee('PRIVATE_BODY_MARKER');
    }

    public function test_entitlement_never_bypasses_restricted_classification(): void
    {
        $post = $this->makePost(['classification' => 'restricted']);
        $user = $this->makeUser();
        (new Entitlement)->forceFill(['user_id' => $user->id, 'post_id' => $post->id])->save();
        $this->actingAs($user)->get('/posts/'.$post->id)->assertNotFound();
    }

    public function test_invoice_uses_server_price_and_fee_and_is_idempotent(): void
    {
        $post = $this->makePost();
        $user = $this->makeUser();
        $key = (string) Str::uuid();
        $this->actingAs($user);
        for ($i = 0; $i < 2; $i++) {
            $this->post('/posts/'.$post->id.'/invoices', ['idempotency_key' => $key, 'gross_units' => 1, 'fee_bps' => 0, 'status' => 'paid'])->assertRedirect();
        }
        $this->assertDatabaseCount('invoices', 1);
        $invoice = Invoice::query()->firstOrFail();
        self::assertSame(20000000, $invoice->gross_units);
        self::assertSame(400000, $invoice->fee_units);
        self::assertSame(19600000, $invoice->creator_units);
        self::assertSame('quote', $invoice->status);
        $this->get('/invoices/'.$invoice->id)->assertSee('Not payable.');
        $this->get('/posts/'.$post->id)->assertDontSee('PRIVATE_BODY_MARKER');
        $this->assertDatabaseCount('entitlements', 0);
    }

    public function test_invoice_snapshot_survives_price_change(): void
    {
        $post = $this->makePost();
        $user = $this->makeUser();
        $key = (string) Str::uuid();
        $service = app(InvoiceService::class);
        $invoice = $service->create($user, $post, $key);
        $post->update(['price_units' => 50000000]);
        self::assertSame(20000000, $service->create($user, $post, $key)->gross_units);
        self::assertSame(50000000, $service->create($user, $post, (string) Str::uuid())->gross_units);
        self::assertSame(20000000, $invoice->fresh()->gross_units);
    }

    public function test_idempotency_key_cannot_be_reused_for_different_post(): void
    {
        $post = $this->makePost();
        $other = $this->makePost();
        $key = (string) Str::uuid();
        $this->actingAs($this->makeUser());
        $this->post('/posts/'.$post->id.'/invoices', ['idempotency_key' => $key])->assertRedirect();
        $this->post('/posts/'.$other->id.'/invoices', ['idempotency_key' => $key])->assertStatus(409);
    }

    public function test_another_member_cannot_read_invoice(): void
    {
        $invoice = app(InvoiceService::class)->create($this->makeUser(), $this->makePost(), (string) Str::uuid());
        $this->actingAs($this->makeUser())->get('/invoices/'.$invoice->id)->assertNotFound();
    }

    public function test_invoice_mutation_is_blocked(): void
    {
        $invoice = app(InvoiceService::class)->create($this->makeUser(), $this->makePost(), (string) Str::uuid());
        $this->expectException(LogicException::class);
        $invoice->forceFill(['status' => 'paid'])->save();
    }

    public function test_non_saleable_posts_cannot_be_quoted(): void
    {
        $this->actingAs($this->makeUser());
        foreach ([['price_units' => 0], ['status' => 'draft'], ['classification' => 'restricted']] as $attributes) {
            $post = $this->makePost($attributes);
            $this->post('/posts/'.$post->id.'/invoices', ['idempotency_key' => (string) Str::uuid()])->assertNotFound();
        }
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_checkout_and_fake_confirmation_never_settle(): void
    {
        $this->actingAs($this->makeUser())->post('/checkout')->assertStatus(503);
        $this->post('/payments/confirm', ['status' => 'paid'])->assertNotFound();
        $this->get('/payment/success?status=paid')->assertNotFound();
        $this->assertDatabaseCount('entitlements', 0);
    }

    public function test_public_report_intake_is_private_to_operator(): void
    {
        $this->post('/report', ['reference' => '/posts/1', 'category' => 'consent', 'description' => 'PRIVATE_REPORT_MARKER for review.'])->assertRedirect('/report');
        $this->assertDatabaseCount('reports', 1);
        $this->get('/')->assertDontSee('PRIVATE_REPORT_MARKER');
        $this->actingAs($this->makeUser())->get('/studio/reports')->assertForbidden();
        $this->actingAsMfa($this->makeUser(true))->get('/studio/reports')->assertSee('PRIVATE_REPORT_MARKER');
    }

    public function test_report_rate_limit_and_validation(): void
    {
        $this->post('/report', ['category' => 'invalid'])->assertSessionHasErrors();
        for ($i = 0; $i < 2; $i++) {
            $this->post('/report', ['reference' => '/posts/1', 'category' => 'safety', 'description' => 'Review this reference.'])->assertRedirect('/report');
        }
        $this->post('/report', ['reference' => '/posts/1', 'category' => 'safety', 'description' => 'Review this reference.'])->assertStatus(429);
    }

    public function test_flags_cannot_enable_unimplemented_payment_adapter(): void
    {
        config(['cms.payments_enabled' => true]);
        $this->expectException(LogicException::class);
        (new AppServiceProvider($this->app))->boot();
    }

    public function test_flags_cannot_enable_restricted_publication(): void
    {
        config(['cms.restricted_publishing_enabled' => true]);
        $this->expectException(LogicException::class);
        (new AppServiceProvider($this->app))->boot();
    }

    public function test_invalid_decimal_and_bad_classification_are_rejected(): void
    {
        $this->actingAsMfa($this->makeUser(true))->post('/studio/posts', $this->postData(['price' => '1e6']))->assertSessionHasErrors('price');
        $this->post('/studio/posts', $this->postData(['classification' => 'anything']))->assertSessionHasErrors('classification');
        $this->assertDatabaseCount('posts', 0);
    }
    public function test_admin_can_progress_report_case_with_audited_state(): void
    {
        $this->post('/report', ['reference' => '/posts/1', 'category' => 'consent', 'description' => 'Review this consent concern.'])->assertRedirect('/report');
        $report = \App\Models\Report::query()->firstOrFail();
        self::assertSame('open', $report->status);

        $this->actingAsMfa($this->makeUser(true))
            ->put('/studio/reports/'.$report->id, ['status' => 'reviewing', 'operator_note' => 'Checking creator-local records.'])
            ->assertRedirect('/studio/reports');
        self::assertSame('reviewing', $report->fresh()->status);
        self::assertNotNull($report->fresh()->reviewed_at);

        $this->put('/studio/reports/'.$report->id, ['status' => 'removed', 'operator_note' => 'Removed pending any appeal.'])
            ->assertRedirect('/studio/reports');
        self::assertSame('removed', $report->fresh()->status);
        self::assertNotNull($report->fresh()->resolved_at);
    }

    public function test_report_case_rejects_invalid_transition_and_member_access(): void
    {
        $this->post('/report', ['reference' => '/posts/1', 'category' => 'safety', 'description' => 'Review this safety concern.'])->assertRedirect('/report');
        $report = \App\Models\Report::query()->firstOrFail();

        $this->actingAs($this->makeUser())->put('/studio/reports/'.$report->id, ['status' => 'reviewing'])->assertForbidden();
        $this->actingAsMfa($this->makeUser(true))->put('/studio/reports/'.$report->id, ['status' => 'removed'])->assertStatus(422);
        self::assertSame('open', $report->fresh()->status);
    }
}
