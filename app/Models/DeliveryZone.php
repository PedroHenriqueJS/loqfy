<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['neighborhoods' => 'array', 'fee' => 'float'];
    }
}
