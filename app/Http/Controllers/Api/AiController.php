<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiRun;

class AiController extends Controller
{
    public function summary(): array
    {
        $usage = AiRun::query()
            ->selectRaw('feature, count(*) as runs, sum(success) as successes, avg(latency_ms) as avg_latency_ms, avg(prompt_tokens + completion_tokens) as avg_tokens')
            ->groupBy('feature')
            ->get()
            ->map(fn ($row) => [
                'feature' => $row->feature,
                'runs' => (int) $row->runs,
                'successes' => (int) $row->successes,
                'avg_latency_ms' => (int) round($row->avg_latency_ms),
                'avg_tokens' => (int) round($row->avg_tokens),
            ]);

        $evals = [];
        foreach (['quote', 'inspection', 'receipt'] as $suite) {
            $path = base_path("evals/results/{$suite}-latest.json");
            $evals[$suite] = file_exists($path) ? json_decode(file_get_contents($path), true) : null;
        }

        return ['model' => config('loqfy.ai.model'), 'usage' => $usage, 'evals' => $evals];
    }
}
