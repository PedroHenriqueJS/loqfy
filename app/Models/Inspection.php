<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inspection extends Model
{
    public const AWAITING_RETURN = 'awaiting_return';

    public const PENDING = 'pending';

    public const ANALYZED = 'analyzed';

    public const FAILED = 'failed';

    public const NO_DAMAGE = 'no_damage';

    public const DAMAGE_CONFIRMED = 'damage_confirmed';

    protected $guarded = [];

    protected $attributes = ['status' => self::AWAITING_RETURN];

    protected function casts(): array
    {
        return ['ai_result' => 'array', 'checks' => 'array', 'decided_at' => 'datetime'];
    }

    public function rentalItem(): BelongsTo
    {
        return $this->belongsTo(RentalItem::class);
    }
}
