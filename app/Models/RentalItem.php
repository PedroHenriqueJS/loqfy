<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RentalItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['daily_rate' => 'float', 'quantity' => 'integer'];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function inspection(): HasOne
    {
        return $this->hasOne(Inspection::class);
    }
}
