<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    protected $table = 'equipment';

    protected $guarded = [];

    public function isInUse(): bool
    {
        return QuoteItem::where('equipment_id', $this->id)->orWhere('substitutes_equipment_id', $this->id)->exists()
            || RentalItem::where('equipment_id', $this->id)->exists();
    }

    protected function casts(): array
    {
        return ['aliases' => 'array', 'daily_rate' => 'float', 'quantity_total' => 'integer'];
    }
}
