<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $post_id
 * @property int $user_id
 * @property string $kind
 * @property string|null $processing_token
 * @property string $state
 * @property string $alt_text
 * @property int $position
 * @property int $source_bytes
 * @property int $reserved_bytes
 * @property int $content_bytes
 * @property int $thumbnail_bytes
 */
final class MediaAsset extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['post_id' => 'integer', 'user_id' => 'integer', 'position' => 'integer', 'source_bytes' => 'integer', 'reserved_bytes' => 'integer', 'content_bytes' => 'integer', 'thumbnail_bytes' => 'integer', 'processing_started_at' => 'datetime'];
    }
}
