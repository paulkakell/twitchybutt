<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\PasswordBytes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class SessionController
{
    public function index(Request $request): View
    {
        $user = $this->user($request);
        $sessions = DB::table('account_sessions')->where('user_id', $user->id)->where('auth_version', $user->auth_version)->whereNull('revoked_at')
            ->where('created_at', '>', now()->subMinutes(config('account_security.absolute_minutes')))
            ->where('last_seen_at', '>', now()->subMinutes(config('account_security.idle_minutes')))
            ->select(['id', 'created_at', 'last_seen_at'])->orderByDesc('created_at')->paginate(20);

        return view('sessions', ['sessions' => $sessions, 'current' => $request->session()->get('account_session_id')]);
    }

    public function revoke(Request $request, string $id): RedirectResponse
    {
        $user = $this->user($request);
        $request->validate(['password' => ['bail', 'required', 'string', new PasswordBytes, 'current_password:web']]);
        abort_unless(DB::table('account_sessions')->where('id', $id)->where('user_id', $user->id)->update(['revoked_at' => now()]) === 1, 404);
        Log::info('cms.session.revoked', ['actor_id' => $user->id]);
        if ($request->session()->get('account_session_id') === $id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login');
        }

        return redirect('/account/sessions')->with('status', 'Session revoked.');
    }

    public function revokeAll(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $request->validate(['password' => ['bail', 'required', 'string', new PasswordBytes, 'current_password:web']]);
        DB::transaction(function () use ($user): void {
            $current = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->auth_version === $user->auth_version, 401);
            $current->auth_version++;
            $current->setRememberToken(Str::random(60));
            $current->save();
            DB::table('account_sessions')->where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            DB::afterCommit(fn () => Log::info('cms.session.revoked', ['actor_id' => $user->id]));
        }, 5);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('status', 'All sessions revoked. Please sign in again.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
