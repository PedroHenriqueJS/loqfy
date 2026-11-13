<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    public const PERSON = 'person';

    public const COMPANY = 'company';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['document_verified_at' => 'datetime'];
    }

    public function isVerified(): bool
    {
        return $this->document_verified_at !== null;
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }
}
