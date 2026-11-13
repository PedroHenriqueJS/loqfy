<?php

namespace App\Http\Resources;

use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer_name,
            'quote_id' => $this->quote_id,
            'origin' => $this->quote?->origin ?? Quote::MANUAL,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'status' => $this->status,
            'total' => $this->total,
            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'equipment_id' => $item->equipment_id,
                'name' => $item->equipment->name,
                'quantity' => $item->quantity,
                'inspection' => $item->inspection ? new InspectionResource($item->inspection) : null,
            ]),
        ];
    }
}
