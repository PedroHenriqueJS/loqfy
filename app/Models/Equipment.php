<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    protected $table = 'equipment';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['aliases' => 'array', 'daily_rate' => 'float', 'quantity_total' => 'integer'];
    }
}
