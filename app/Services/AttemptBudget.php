<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AttemptBudget
{
    /**
     * Consume ordered scopes atomically. Place the IP scope first so a blocked IP
     * cannot create unlimited account-key rows. Rejections still consume earlier scopes.
     *
     * @param  list<array{string, int, int}>  $scopes
     */
    public function consume(array $scopes): bool
    {
        if ($scopes === [] || count($scopes) > 3) {
            throw new InvalidArgumentException('Invalid attempt budget.');
        }

        return DB::transaction(function () use ($scopes): bool {
            $now = now()->getTimestamp();
            foreach ($scopes as [$scope, $limit, $seconds]) {
                if ($limit < 1 || $limit > 1000 || $seconds < 1 || $seconds > 86400) {
                    throw new InvalidArgumentException('Invalid attempt budget bounds.');
                }
                $key = hash_hmac('sha256', $scope, (string) config('app.key'));
                DB::table('attempt_budgets')->insertOrIgnore(['key' => $key, 'attempts' => 0, 'expires_at' => $now + $seconds]);
                $row = DB::table('attempt_budgets')->where('key', $key)->lockForUpdate()->first();
                if ($row === null) {
                    throw new \RuntimeException('Attempt budget storage unavailable.');
                }
                $expired = (int) $row->expires_at <= $now;
                $attempts = $expired ? 0 : (int) $row->attempts;
                if ($attempts >= $limit) {
                    return false;
                }
                DB::table('attempt_budgets')->where('key', $key)->update(['attempts' => $attempts + 1, 'expires_at' => $expired ? $now + $seconds : (int) $row->expires_at]);
            }

            return true;
        }, 5);
    }
}
