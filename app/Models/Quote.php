<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Quote extends Model
{
    public const AI = 'ai';

    public const MANUAL = 'manual';

    protected $guarded = [];

    protected $attributes = ['status' => 'draft', 'origin' => self::MANUAL, 'needs_review' => false];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'issues' => 'array',
            'needs_review' => 'boolean',
            'delivery_fee' => 'float',
            'subtotal' => 'float',
            'discount' => 'float',
            'total' => 'float',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function rentalRequest(): BelongsTo
    {
        return $this->belongsTo(RentalRequest::class);
    }

    public function rental(): HasOne
    {
        return $this->hasOne(Rental::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }
}
