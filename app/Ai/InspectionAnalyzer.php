<?php

namespace App\Ai;

use App\Support\Photo;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\ValueObjects\Media\Image;

class InspectionAnalyzer
{
    public function analyze(string $checkoutPhotoPath, string $returnPhotoPath, string $equipmentName): AgentResult
    {
        $startedAt = microtime(true);
        $maxSide = config('loqfy.inspection_photo_max_side');

        $response = Prism::structured()
            ->using(config('loqfy.ai.provider'), config('loqfy.ai.model'))
            ->usingTemperature(0)
            ->withMaxTokens(700)
            ->withClientRetry(ProviderRetry::ATTEMPTS, ProviderRetry::delayMs(...), ProviderRetry::shouldRetry(...), throw: true)
            ->withSystemPrompt($this->prompt())
            ->withPrompt("Equipamento: {$equipmentName}. A primeira foto é a de SAÍDA e a segunda é a de DEVOLUÇÃO.", [
                Image::fromBase64(base64_encode(Photo::resizedJpeg($checkoutPhotoPath, $maxSide)), 'image/jpeg'),
                Image::fromBase64(base64_encode(Photo::resizedJpeg($returnPhotoPath, $maxSide)), 'image/jpeg'),
            ])
            ->withSchema($this->schema())
            ->asStructured();

        return new AgentResult(
            extraction: $response->structured ?: null,
            rawText: $response->text,
            toolCalls: [],
            steps: 1,
            promptTokens: $response->usage->promptTokens,
            completionTokens: $response->usage->completionTokens,
            latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
            error: empty($response->structured) ? 'The model returned an empty answer.' : null,
        );
    }

    private function prompt(): string
    {
        return <<<'PROMPT'
        Você faz a triagem da vistoria de devolução em uma locadora de máquinas e ferramentas. Recebe duas fotos do mesmo equipamento: a de saída, tirada quando ele foi entregue ao cliente, e a de devolução. Um funcionário confere o seu resultado antes de qualquer cobrança.

        Regras:
        - Aponte apenas danos NOVOS: o que aparece na foto de devolução e não aparecia na de saída. Amassados, trincas, peças quebradas, peças faltando, cabos cortados.
        - Desgaste, sujeira, ferrugem ou tinta descascada que já estavam na foto de saída não contam.
        - Sujeira de uso (cimento, barro, poeira) não é dano.
        - Diferenças de ângulo, luz ou fundo não são dano. Se uma parte não aparece em uma das fotos, não conclua nada sobre ela.
        - Se as fotos não parecem ser do mesmo equipamento, marque same_equipment como false e não liste danos.
        - Se a foto estiver escura, tremida ou longe demais para avaliar, marque photo_quality como poor.
        - Na dúvida, liste o ponto com confidence low em vez de omitir. O funcionário decide.
        PROMPT;
    }

    private function schema(): ObjectSchema
    {
        return new ObjectSchema('inspection', 'Resultado da comparação das duas fotos', [
            new BooleanSchema('same_equipment', 'true se as duas fotos parecem ser do mesmo equipamento'),
            new ArraySchema('damages', 'Danos novos encontrados; lista vazia se não houver', new ObjectSchema('damage', 'Um dano', [
                new StringSchema('location', 'Parte do equipamento, em português'),
                new StringSchema('description', 'O que mudou em relação à foto de saída, em português'),
                new EnumSchema('severity', 'Gravidade', ['minor', 'moderate', 'severe']),
                new EnumSchema('confidence', 'Confiança', ['low', 'medium', 'high']),
            ], ['location', 'description', 'severity', 'confidence'])),
            new EnumSchema('photo_quality', 'Qualidade das fotos para avaliação', ['ok', 'poor']),
            new StringSchema('summary', 'Resumo em uma ou duas frases, em português'),
        ], ['same_equipment', 'damages', 'photo_quality', 'summary']);
    }
}
