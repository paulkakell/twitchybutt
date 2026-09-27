<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * @property string $id
 * @property int $user_id
 * @property int $post_id
 * @property string $idempotency_key
 * @property int $gross_units
 * @property int $fee_units
 * @property int $creator_units
 * @property string $token
 * @property string $status
 * @property \Illuminate\Support\Carbon $expires_at
 */
class Invoice extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'post_id' => 'integer', 'gross_units' => 'integer', 'fee_units' => 'integer', 'creator_units' => 'integer', 'expires_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Invoice snapshots cannot be updated.'));
        static::deleting(fn () => throw new LogicException('Invoice snapshots cannot be deleted.'));
    }
}
