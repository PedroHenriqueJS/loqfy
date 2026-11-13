<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'origin' => $this->origin,
            'needs_review' => $this->needs_review,
            'rental_request_id' => $this->rental_request_id,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer_name,
            'customer_verified' => $this->customer?->isVerified() ?? false,
            'rental_id' => $this->status === 'approved' ? $this->rental?->id : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'days' => $this->days,
            'neighborhood' => $this->neighborhood,
            'delivery_zone' => $this->deliveryZone?->name,
            'delivery_fee' => $this->delivery_fee,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
            'confidence' => $this->confidence,
            'issues' => $this->issues,
            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'equipment_id' => $item->equipment_id,
                'name' => $item->equipment->name,
                'requested_term' => $item->requested_term,
                'substitutes' => $item->substitutes?->name,
                'quantity' => $item->quantity,
                'daily_rate' => $item->daily_rate,
                'line_total' => $item->line_total,
                'available' => $item->available,
            ]),
        ];
    }
}
