<?php

namespace App\Jobs;

use App\Ai\InspectionAnalyzer;
use App\Models\Inspection;
use App\Services\AiRunLog;
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

    public function handle(InspectionAnalyzer $analyzer, AiRunLog $log): void
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

        $log->record('inspection', $result, $inspection);

        $inspection->update($result->extraction === null
            ? ['status' => Inspection::FAILED, 'error' => $result->error]
            : ['status' => Inspection::ANALYZED, 'ai_result' => $result->extraction, 'error' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->inspection->update(['status' => Inspection::FAILED, 'error' => $exception?->getMessage()]);
    }
}
