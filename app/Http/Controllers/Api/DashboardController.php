<?php

namespace App\Http\Controllers\Api;

use App\Ai\InsightsAnalyzer;
use App\Http\Controllers\Controller;
use App\Models\AiRun;
use App\Services\DashboardReport;
use Prism\Prism\Exceptions\PrismException;

class DashboardController extends Controller
{
    public function show(DashboardReport $report): array
    {
        return ['metrics' => $report->metrics(), 'analysis' => $this->lastAnalysis()];
    }

    public function analyze(DashboardReport $report, InsightsAnalyzer $analyzer): array
    {
        try {
            $result = $analyzer->analyze($report->metrics());
        } catch (PrismException $e) {
            abort(503, 'A análise não pôde ser gerada agora. Tente de novo em um minuto.');
        }

        AiRun::create([
            'feature' => 'insights',
            'model' => config('loqfy.ai.model'),
            'output' => $result->extraction ?? ['raw' => $result->rawText],
            'steps' => $result->steps,
            'prompt_tokens' => $result->promptTokens,
            'completion_tokens' => $result->completionTokens,
            'latency_ms' => $result->latencyMs,
            'success' => $result->error === null,
            'error' => $result->error,
        ]);

        abort_if($result->extraction === null, 503, 'A IA não devolveu uma análise legível. Tente de novo.');

        return ['analysis' => $this->lastAnalysis()];
    }

    private function lastAnalysis(): ?array
    {
        $run = AiRun::where('feature', 'insights')->where('success', true)->latest('id')->first();

        return $run ? $run->output + ['generated_at' => $run->created_at->toIso8601String(), 'model' => $run->model] : null;
    }
}
