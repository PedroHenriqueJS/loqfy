<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rental extends Model
{
    public const RESERVED = 'reserved';

    public const OUT = 'out';

    public const RETURNED = 'returned';

    public const HOLDING = [self::RESERVED, self::OUT];

    protected $guarded = [];

    protected $attributes = ['status' => self::RESERVED];

    protected function casts(): array
    {
        return ['start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d', 'total' => 'float'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(RentalItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
