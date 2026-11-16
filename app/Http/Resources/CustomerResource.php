<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'kind' => $this->kind,
            'cnpj' => $this->cnpj,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'notes' => $this->notes,
            'document_verified_at' => $this->document_verified_at?->toIso8601String(),
            'quotes_count' => $this->whenCounted('quotes'),
            'rentals_count' => $this->whenCounted('rentals'),
        ];
    }
}
