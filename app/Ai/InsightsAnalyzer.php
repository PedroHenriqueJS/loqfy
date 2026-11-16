<?php

namespace App\Ai;

use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

class InsightsAnalyzer
{
    public function analyze(array $metrics): AgentResult
    {
        $startedAt = microtime(true);

        $response = Prism::structured()
            ->using(config('loqfy.ai.provider'), config('loqfy.ai.model'))
            ->usingTemperature(0.2)
            ->withMaxTokens(900)
            ->withClientRetry(ProviderRetry::ATTEMPTS, ProviderRetry::delayMs(...), ProviderRetry::shouldRetry(...), throw: true)
            ->withSystemPrompt($this->prompt())
            ->withPrompt("Dados da locadora:\n".json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            ->withSchema($this->schema())
            ->asStructured();

        $analysis = $response->structured ?: null;

        if ($analysis !== null) {
            $known = $this->numbersIn($metrics);
            $analysis['insights'] = array_map(function (array $insight) use ($known) {
                $text = implode(' ', [$insight['title'] ?? '', $insight['evidence'] ?? '', $insight['action'] ?? '']);
                $insight['unverified_numbers'] = $this->numbersNotIn($text, $known);

                return $insight;
            }, $analysis['insights'] ?? []);
        }

        return new AgentResult(
            extraction: $analysis,
            rawText: $response->text,
            toolCalls: [],
            steps: 1,
            promptTokens: $response->usage->promptTokens,
            completionTokens: $response->usage->completionTokens,
            latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
            error: $analysis === null ? 'The model returned an empty answer.' : null,
        );
    }

    private function prompt(): string
    {
        return <<<'PROMPT'
        Você analisa os números de uma locadora de máquinas e ferramentas para o dono do negócio. Recebe um JSON com os indicadores de hoje. Escreva uma leitura curta e prática, em português do Brasil.

        Regras:
        - Use somente o que está nos dados. Não invente fatos, causas nem números.
        - Não faça contas. Cite os números exatamente como aparecem nos dados, sem arredondar, somar ou calcular percentuais.
        - Valores em reais vão no formato R$ 1.234,56.
        - Escreva de 3 a 5 insights, do mais importante para o menos importante. Cada um tem um título curto, a evidência (os números que sustentam) e uma ação concreta para esta semana.
        - kind é "risk" para algo que pode custar dinheiro ou cliente, "opportunity" para algo que pode render mais, e "info" para o resto.
        - Se os dados forem poucos para uma conclusão, diga isso em vez de forçar um insight.
        - headline é uma frase que resume a situação da locadora hoje.
        - Não use os nomes dos campos do JSON no texto. Escreva como quem fala com o dono.
        PROMPT;
    }

    private function schema(): ObjectSchema
    {
        return new ObjectSchema('analysis', 'Leitura dos indicadores', [
            new StringSchema('headline', 'Resumo em uma frase'),
            new ArraySchema('insights', 'De 3 a 5 insights', new ObjectSchema('insight', 'Um insight', [
                new EnumSchema('kind', 'Tipo', ['risk', 'opportunity', 'info']),
                new StringSchema('title', 'Título curto'),
                new StringSchema('evidence', 'Números dos dados que sustentam o insight'),
                new StringSchema('action', 'O que fazer nesta semana'),
            ], ['kind', 'title', 'evidence', 'action'])),
        ], ['headline', 'insights']);
    }

    private function numbersIn(array $data): array
    {
        $numbers = [];

        array_walk_recursive($data, function (mixed $value) use (&$numbers) {
            if (is_int($value) || is_float($value)) {
                $numbers[] = (float) $value;
            } elseif (is_string($value)) {
                foreach (preg_split('/\D+/', $value, -1, PREG_SPLIT_NO_EMPTY) as $part) {
                    $numbers[] = (float) $part;
                }
            }
        });

        return $numbers;
    }

    private function numbersNotIn(string $text, array $known): array
    {
        preg_match_all('/\d[\d.]*(?:,\d+)?/', $text, $matches);
        $missing = [];

        foreach ($matches[0] as $written) {
            $written = rtrim($written, '.');
            $value = str_contains($written, ',')
                ? (float) str_replace(['.', ','], ['', '.'], $written)
                : (preg_match('/^\d+\.\d{1,2}$/', $written) ? (float) $written : (float) str_replace('.', '', $written));

            $found = false;
            foreach ($known as $number) {
                if (abs($number - $value) < 0.006 || abs(round($number) - $value) < 0.006 || abs(round($number, 1) - $value) < 0.006) {
                    $found = true;
                    break;
                }
            }

            if (! $found && ! in_array($value, [30.0, 90.0], true)) {
                $missing[] = $written;
            }
        }

        return array_values(array_unique($missing));
    }
}
