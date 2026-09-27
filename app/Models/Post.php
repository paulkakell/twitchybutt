<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string $body
 * @property string $classification
 * @property string $status
 * @property int $price_units
 */
class Post extends Model
{
    protected $fillable = ['title', 'body', 'classification', 'status', 'price_units'];

    protected function casts(): array
    {
        return ['price_units' => 'integer'];
    }
}
