<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class SecurityMaintenance
{
    /** @return array{sessions: int, pending_factors: int, budgets: int} */
    public function prune(): array
    {
        // Absolute lifetimes never exceed one day; retain only seven days of expired metadata.
        $sessions = DB::table('account_sessions')->where('created_at', '<', now()->subDays(7))->delete();
        $pending = DB::table('users')->where('mfa_pending_expires_at', '<=', now())
            ->update(['mfa_pending_secret' => null, 'mfa_pending_expires_at' => null, 'mfa_pending_purpose' => null, 'mfa_pending_version' => null]);

        return ['sessions' => $sessions, 'pending_factors' => $pending, 'budgets' => DB::table('attempt_budgets')->where('expires_at', '<', now()->subDay()->getTimestamp())->delete()];
    }
}
