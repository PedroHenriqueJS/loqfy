<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentalRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_name' => $this->customer_name,
            'channel' => $this->channel,
            'raw_text' => $this->raw_text,
            'status' => $this->status,
            'error' => $this->error,
            'created_at' => $this->created_at->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'quote' => $this->whenLoaded('quote', fn () => $this->quote ? new QuoteResource($this->quote) : null),
            'ai_run' => $this->when($this->relationLoaded('latestAiRun'), fn () => $this->latestAiRun ? [
                'model' => $this->latestAiRun->model,
                'tool_calls' => $this->latestAiRun->tool_calls,
                'tokens' => $this->latestAiRun->prompt_tokens + $this->latestAiRun->completion_tokens,
                'latency_ms' => $this->latestAiRun->latency_ms,
                'success' => $this->latestAiRun->success,
            ] : null),
        ];
    }
}
