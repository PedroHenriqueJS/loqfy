<?php

namespace App\Services;

use App\Ai\AgentResult;
use App\Models\AiRun;
use Illuminate\Database\Eloquent\Model;

class AiRunLog
{
    public function record(string $feature, AgentResult $result, ?Model $subject = null): AiRun
    {
        return AiRun::create([
            'feature' => $feature,
            'model' => config('loqfy.ai.model'),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'output' => $result->extraction ?? ['raw' => $result->rawText],
            'tool_calls' => $result->toolCalls ?: null,
            'steps' => $result->steps,
            'prompt_tokens' => $result->promptTokens,
            'completion_tokens' => $result->completionTokens,
            'latency_ms' => $result->latencyMs,
            'success' => $result->error === null,
            'error' => $result->error,
        ]);
    }
}
