<?php

namespace App\Jobs;

use App\Ai\QuoteAgent;
use App\Models\Quote;
use App\Models\RentalRequest;
use App\Services\AiRunLog;
use App\Services\QuoteBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Throwable;

class ProcessRentalRequest implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public int $timeout = 180;

    public function __construct(public RentalRequest $rentalRequest) {}

    public function handle(QuoteAgent $agent, QuoteBuilder $builder, AiRunLog $log): void
    {
        $request = $this->rentalRequest;
        $request->update(['status' => RentalRequest::PROCESSING, 'error' => null]);

        try {
            $result = $agent->run($request->raw_text, CarbonImmutable::parse($request->created_at)->startOfDay());
        } catch (PrismRateLimitedException $e) {
            $request->update(['status' => RentalRequest::PENDING]);
            $this->release(max(15, (int) $e->retryAfter + 2));

            return;
        }

        $log->record('quote', $result, $request);

        $extraction = $result->extraction ?? [
            'issues' => [['type' => 'missing_info', 'message' => 'A IA não devolveu uma resposta legível. Preencha o orçamento manualmente.']],
            'confidence' => 'low',
        ];

        $request->update(['extraction' => $result->extraction, 'processed_at' => now()]);

        $quote = Quote::where('rental_request_id', $request->id)->where('status', 'draft')->first()
            ?? new Quote(['rental_request_id' => $request->id, 'origin' => Quote::AI]);

        $builder->build($quote, $extraction);
    }

    public function failed(?Throwable $exception): void
    {
        $this->rentalRequest->update([
            'status' => RentalRequest::FAILED,
            'error' => $exception?->getMessage(),
        ]);
    }
}
