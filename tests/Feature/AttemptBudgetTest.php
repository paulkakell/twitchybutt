<?php

namespace Tests\Feature;

use App\Services\AttemptBudget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AttemptBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_enforces_limit_and_exact_deadline(): void
    {
        $this->freezeTime();
        $budget = app(AttemptBudget::class);
        for ($i = 0; $i < 5; $i++) {
            self::assertTrue($budget->consume([['example', 5, 60]]));
        }
        self::assertFalse($budget->consume([['example', 5, 60]]));
        $this->travel(60)->seconds();
        self::assertTrue($budget->consume([['example', 5, 60]]));
    }

    public function test_blocked_first_scope_does_not_create_more_account_rows(): void
    {
        $budget = app(AttemptBudget::class);
        self::assertTrue($budget->consume([['ip', 1, 60], ['private@example.test', 5, 60]]));
        for ($i = 0; $i < 10; $i++) {
            self::assertFalse($budget->consume([['ip', 1, 60], ['other-'.$i, 5, 60]]));
        }
        $this->assertDatabaseCount('attempt_budgets', 2);
        self::assertStringNotContainsString('private@example.test', DB::table('attempt_budgets')->get()->toJson());
    }

    public function test_rejected_account_still_consumes_ip_scope(): void
    {
        $budget = app(AttemptBudget::class);
        self::assertTrue($budget->consume([['ip', 3, 60], ['account', 1, 60]]));
        self::assertFalse($budget->consume([['ip', 3, 60], ['account', 1, 60]]));
        self::assertFalse($budget->consume([['ip', 3, 60], ['account', 1, 60]]));
        self::assertFalse($budget->consume([['ip', 3, 60], ['new-account', 1, 60]]));
        $this->assertDatabaseCount('attempt_budgets', 2);
    }

    public function test_budgets_are_site_local_and_purged_after_expiry(): void
    {
        app(AttemptBudget::class)->consume([['example', 5, 60]]);
        $this->travel(2)->days();
        $this->artisan('cms:security-prune')->assertSuccessful();
        $this->assertDatabaseCount('attempt_budgets', 0);
    }
}
