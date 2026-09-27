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
 * @property Carbon|null $password_changed_at
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token', 'auth_version'];

    protected $attributes = ['auth_version' => 1, 'is_admin' => false];

    protected function casts(): array
    {
        return ['is_admin' => 'boolean', 'password' => 'hashed', 'auth_version' => 'integer', 'email_verified_at' => 'datetime', 'password_changed_at' => 'datetime'];
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
