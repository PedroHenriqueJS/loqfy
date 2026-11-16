<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'category' => $this->category,
            'description' => $this->description,
            'amount' => $this->amount,
            'due_date' => $this->due_date->toDateString(),
            'paid_at' => $this->paid_at?->toDateString(),
            'status' => $this->status(),
            'rental_id' => $this->rental_id,
            'origin' => $this->origin ?? 'manual',
            'receipt_photo' => $this->receipt_photo,
            'receipt_photo_url' => $this->receipt_photo ? '/storage/'.$this->receipt_photo : null,
        ];
    }
}
