<?php

namespace App\Models;

use App\Notifications\AccountLink;
use App\Services\AccountSecurityService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property bool $is_admin
 * @property int $auth_version
 * @property Carbon|null $email_verified_at
 * @property string|null $mfa_secret
 * @property int|null $mfa_pending_version
 * @property string|null $mfa_pending_purpose
 * @property string|null $mfa_pending_secret
 * @property Carbon|null $mfa_pending_expires_at
 * @property Carbon|null $mfa_confirmed_at
 * @property int|null $mfa_last_counter
 * @property Carbon|null $password_changed_at
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token', 'auth_version', 'mfa_secret', 'mfa_pending_secret', 'mfa_last_counter', 'mfa_pending_expires_at', 'mfa_pending_version', 'mfa_pending_purpose'];

    protected $attributes = ['auth_version' => 1, 'is_admin' => false];

    protected function casts(): array
    {
        return ['is_admin' => 'boolean', 'password' => 'hashed', 'auth_version' => 'integer', 'email_verified_at' => 'datetime', 'password_changed_at' => 'datetime', 'mfa_secret' => 'encrypted', 'mfa_pending_secret' => 'encrypted', 'mfa_pending_version' => 'integer', 'mfa_confirmed_at' => 'datetime', 'mfa_pending_expires_at' => 'datetime', 'mfa_last_counter' => 'integer'];
    }

    public function hasMfa(): bool
    {
        // Missing key material must not silently disable a confirmed factor.
        return $this->mfa_confirmed_at !== null;
    }

    /** @param string $token */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new AccountLink('reset', AccountSecurityService::origin().'/reset-password?token='.rawurlencode($token)));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new AccountLink('verify', app(AccountSecurityService::class)->verificationUrl($this)));
    }
}
