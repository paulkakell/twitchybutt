<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class SessionRegistry
{
    public static function validateConfiguration(): void
    {
        $idle = config('account_security.idle_minutes');
        $absolute = config('account_security.absolute_minutes');
        if (! is_int($idle) || ! is_int($absolute) || $idle < 5 || $idle > 120 || $absolute < $idle || $absolute > 1440) {
            throw new LogicException('Session idle must be 5-120 minutes; absolute must be idle-1440 minutes.');
        }
    }

    public function start(Session $session, User $user): string
    {
        $id = (string) Str::uuid();
        DB::table('account_sessions')->insert(['id' => $id, 'user_id' => $user->id, 'auth_version' => $user->auth_version, 'created_at' => now(), 'last_seen_at' => now()]);
        $session->put(['auth_user_id' => (string) $user->id, 'auth_version' => $user->auth_version, 'account_session_id' => $id]);
        $session->forget(['mfa_verified_version', 'mfa_verified_user', 'mfa_verified_at']);

        return $id;
    }

    public function touch(Session $session, User $user): bool
    {
        $id = $session->get('account_session_id');
        if (! is_string($id) || ! Str::isUuid($id)) {
            return false;
        }

        return DB::table('account_sessions')->where('id', $id)->where('user_id', $user->id)
            ->where('auth_version', $user->auth_version)->whereNull('revoked_at')
            ->where('created_at', '>', now()->subMinutes(config('account_security.absolute_minutes')))
            ->where('last_seen_at', '>', now()->subMinutes(config('account_security.idle_minutes')))
            ->update(['last_seen_at' => now()]) === 1;
    }

    public function revokeCurrent(Session $session, User $user): void
    {
        DB::table('account_sessions')->where('id', $session->get('account_session_id'))->where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }
}
