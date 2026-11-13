<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['daily_rate' => 'float', 'line_total' => 'float', 'available' => 'boolean', 'quantity' => 'integer'];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function substitutes(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'substitutes_equipment_id');
    }
}
