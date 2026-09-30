<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = ['reference', 'category', 'description'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'resolved_at' => 'datetime'];
    }
}
