<?php

namespace App\Jobs;

use App\Ai\InspectionAnalyzer;
use App\Models\AiRun;
use App\Models\Inspection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Throwable;

class AnalyzeInspection implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public int $timeout = 180;

    public function __construct(public Inspection $inspection) {}

    public function handle(InspectionAnalyzer $analyzer): void
    {
        $inspection = $this->inspection->load('rentalItem.equipment');
        $disk = Storage::disk('public');

        try {
            $result = $analyzer->analyze(
                $disk->path($inspection->checkout_photo),
                $disk->path($inspection->return_photo),
                $inspection->rentalItem->equipment->name,
            );
        } catch (PrismRateLimitedException $e) {
            $this->release(max(20, (int) $e->retryAfter + 2));

            return;
        }

        AiRun::create([
            'feature' => 'inspection',
            'model' => config('loqfy.ai.model'),
            'subject_type' => $inspection->getMorphClass(),
            'subject_id' => $inspection->id,
            'output' => $result->extraction ?? ['raw' => $result->rawText],
            'steps' => $result->steps,
            'prompt_tokens' => $result->promptTokens,
            'completion_tokens' => $result->completionTokens,
            'latency_ms' => $result->latencyMs,
            'success' => $result->error === null,
            'error' => $result->error,
        ]);

        $inspection->update($result->extraction === null
            ? ['status' => Inspection::FAILED, 'error' => $result->error]
            : ['status' => Inspection::ANALYZED, 'ai_result' => $result->extraction, 'error' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->inspection->update(['status' => Inspection::FAILED, 'error' => $exception?->getMessage()]);
    }
}
