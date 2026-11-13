<?php

namespace App\Ai;

readonly class AgentResult
{
    public function __construct(
        public ?array $extraction,
        public string $rawText,
        public array $toolCalls,
        public int $steps,
        public int $promptTokens,
        public int $completionTokens,
        public int $latencyMs,
        public ?string $error = null,
    ) {}
}
