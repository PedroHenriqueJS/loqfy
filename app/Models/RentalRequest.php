<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class RentalRequest extends Model
{
    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const READY = 'ready';

    public const NEEDS_REVIEW = 'needs_review';

    public const APPROVED = 'approved';

    public const DISCARDED = 'discarded';

    public const FAILED = 'failed';

    protected $guarded = [];

    protected $attributes = ['status' => self::PENDING, 'channel' => 'whatsapp'];

    protected function casts(): array
    {
        return ['extraction' => 'array', 'processed_at' => 'datetime'];
    }

    public function quote(): HasOne
    {
        return $this->hasOne(Quote::class)->latestOfMany();
    }

    public function latestAiRun(): MorphOne
    {
        return $this->morphOne(AiRun::class, 'subject')->latestOfMany();
    }
}
