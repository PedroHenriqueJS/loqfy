<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rental_item_id' => $this->rental_item_id,
            'status' => $this->status,
            'origin' => $this->ai_result !== null || $this->return_photo !== null ? 'ai' : 'manual',
            'checkout_photo_url' => $this->checkout_photo ? '/storage/'.$this->checkout_photo : null,
            'return_photo_url' => $this->return_photo ? '/storage/'.$this->return_photo : null,
            'ai_result' => $this->ai_result,
            'checks' => $this->checks ?? [],
            'error' => $this->error,
            'decision' => $this->decision,
            'decision_note' => $this->decision_note,
            'decided_at' => $this->decided_at?->toIso8601String(),
        ];
    }
}
