<?php

namespace App\Ai;

use App\Ai\Tools\QuoteTools;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Structured\PendingRequest as PendingStructuredRequest;
use Prism\Prism\Text\PendingRequest;
use Prism\Prism\Text\Response;
use Throwable;

class QuoteAgent
{
    public function __construct(private QuoteTools $tools) {}

    public function run(string $requestText, CarbonImmutable $today): AgentResult
    {
        $startedAt = microtime(true);

        $lookup = $this->configure(Prism::text())
            ->withSystemPrompt($this->lookupPrompt($today))
            ->withPrompt($requestText)
            ->withTools($this->tools->all())
            ->withMaxSteps(1)
            ->asText();

        $toolCalls = $this->toolCalls($lookup);
        $promptTokens = $lookup->usage->promptTokens;
        $completionTokens = $lookup->usage->completionTokens;
        $extraction = null;
        $rawText = '';
        $error = null;

        try {
            $answer = $this->configure(Prism::structured())
                ->withSystemPrompt($this->answerPrompt($today))
                ->withPrompt($this->answerInput($requestText, $toolCalls))
                ->withSchema($this->schema())
                ->asStructured();

            $extraction = $answer->structured;
            $rawText = $answer->text;
            $promptTokens += $answer->usage->promptTokens;
            $completionTokens += $answer->usage->completionTokens;
        } catch (PrismException $e) {
            $error = $e->getMessage();
        }

        if ($error === null && empty($extraction)) {
            $error = 'The model returned an empty answer.';
        }

        return new AgentResult(
            extraction: $extraction ?: null,
            rawText: $rawText,
            toolCalls: $toolCalls,
            steps: 2,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
            error: $error,
        );
    }

    private function configure(PendingRequest|PendingStructuredRequest $request): PendingRequest|PendingStructuredRequest
    {
        return $request
            ->using(config('loqfy.ai.provider'), config('loqfy.ai.model'))
            ->usingTemperature(0)
            ->withMaxTokens(700)
            ->withClientRetry(4, fn (int $attempt, Throwable $e): int => self::retryDelayMs($e), fn (Throwable $e): bool => self::isRateLimit($e), throw: true);
    }

    private static function isRateLimit(Throwable $e): bool
    {
        return $e instanceof RequestException && $e->response->status() === 429;
    }

    private static function retryDelayMs(Throwable $e): int
    {
        $seconds = $e instanceof RequestException ? (float) $e->response->header('retry-after') : 0;

        return (int) ((max($seconds, 2) + 1) * 1000);
    }

    private function toolCalls(Response $response): array
    {
        $calls = [];

        foreach ($response->steps as $step) {
            foreach ($step->toolResults as $result) {
                $calls[] = [
                    'name' => $result->toolName,
                    'arguments' => $result->args,
                    'result' => is_string($result->result) ? (json_decode($result->result, true) ?? $result->result) : $result->result,
                ];
            }
        }

        return $calls;
    }

    private function answerInput(string $requestText, array $toolCalls): string
    {
        $results = $toolCalls === []
            ? 'Nenhuma consulta foi feita.'
            : collect($toolCalls)
                ->map(fn (array $call) => $call['name'].': '.json_encode($call['result'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                ->implode("\n");

        return "Pedido do cliente:\n{$requestText}\n\nResultados das consultas já feitas:\n{$results}";
    }

    private function lookupPrompt(CarbonImmutable $today): string
    {
        return <<<PROMPT
        Você ajuda o atendente de uma locadora de máquinas e ferramentas para construção. Recebe o pedido de um cliente em texto livre. Sua tarefa agora é só consultar o sistema: chame as ferramentas necessárias, todas de uma vez. Não escreva resposta para o cliente.

        {$this->dateRules($today)}

        Consultas:
        - Chame search_equipment uma vez para cada equipamento pedido, com o termo que o cliente usou, a quantidade (1 se ele não disse) e as datas. Se o período não estiver claro, chame sem datas.
        - Chame find_delivery_zone se o cliente citou bairro, cidade ou pediu entrega.
        - Se a mensagem não for um pedido de locação, não chame nada.
        PROMPT;
    }

    private function answerPrompt(CarbonImmutable $today): string
    {
        return <<<PROMPT
        Você ajuda o atendente de uma locadora de máquinas e ferramentas para construção. Recebe o pedido de um cliente em texto livre e os resultados das consultas já feitas no sistema. Escreva os dados do pedido de forma estruturada. Um atendente humano revisa antes de responder ao cliente. Não é possível fazer novas consultas.

        {$this->dateRules($today)}

        Regras:
        - Nunca informe nem calcule preços. O sistema calcula.
        - Use somente equipment_id que aparece nos resultados das consultas. Em cada consulta, o primeiro resultado é o mais parecido com o termo.
        - Se a consulta não achou nada para um termo, não inclua o item e registre um problema not_in_catalog citando o termo.
        - Se a consulta devolveu mais de um equipamento que serve para o termo (por exemplo, tamanhos diferentes) e o cliente não disse qual, use o primeiro e registre um problema ambiguous citando as opções.
        - Se o equipamento escolhido está com "available": false, veja as alternativas dele. Se uma delas serve ao mesmo trabalho e está disponível, use o id dela em equipment_id e ponha o id do equipamento original em substitutes_equipment_id. Se nenhuma serve, mantenha o original. Nos dois casos registre um problema unavailable.
        - Quantidade não informada é 1.
        - Se o período não estiver claro, use null nas duas datas e registre um problema missing_info.
        - delivery é true quando o cliente pede entrega ou informa o local da obra, e false quando diz que retira na loja ou não fala de local.
        - Nas mensagens dos problemas, cite os equipamentos pelo nome, sem o id.
        - confidence é low quando você teve que adivinhar algo importante.
        PROMPT;
    }

    private function dateRules(CarbonImmutable $today): string
    {
        $weekdays = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];

        $calendar = collect(range(0, 13))
            ->map(fn (int $offset) => $today->addDays($offset))
            ->map(fn (CarbonImmutable $day) => $weekdays[$day->dayOfWeek].' '.$day->toDateString())
            ->implode(', ');

        return <<<RULES
        Hoje é {$weekdays[$today->dayOfWeek]}, {$today->toDateString()}. Próximos dias: {$calendar}.

        Datas:
        - start_date é o primeiro dia de uso e end_date é o último, no formato AAAA-MM-DD.
        - "Amanhã" sem duração é um dia só, com as duas datas iguais. "Por 3 dias a partir de segunda" vai de segunda a quarta. "Fim de semana" é o próximo sábado e domingo.
        - Data sem ano é a próxima ocorrência dessa data.
        RULES;
    }

    private function schema(): ObjectSchema
    {
        return new ObjectSchema('rental_request', 'Dados do pedido de locação', [
            new StringSchema('customer_name', 'Nome do cliente, se ele disse', nullable: true),
            new ArraySchema('items', 'Equipamentos pedidos', new ObjectSchema('item', 'Um equipamento', [
                new StringSchema('requested_term', 'Termo que o cliente usou'),
                new NumberSchema('equipment_id', 'Id do catálogo'),
                new NumberSchema('quantity', 'Quantidade'),
                new NumberSchema('substitutes_equipment_id', 'Id do equipamento indisponível que este item substitui', nullable: true),
            ], ['requested_term', 'equipment_id', 'quantity', 'substitutes_equipment_id'])),
            new StringSchema('start_date', 'Primeiro dia de uso, AAAA-MM-DD', nullable: true),
            new StringSchema('end_date', 'Último dia de uso, AAAA-MM-DD', nullable: true),
            new BooleanSchema('delivery', 'true se a locadora deve entregar'),
            new StringSchema('neighborhood', 'Bairro ou cidade da entrega', nullable: true),
            new ArraySchema('issues', 'Problemas que o atendente precisa ver', new ObjectSchema('issue', 'Um problema', [
                new EnumSchema('type', 'Tipo', ['unavailable', 'not_in_catalog', 'ambiguous', 'missing_info']),
                new StringSchema('message', 'Explicação curta em português'),
            ], ['type', 'message'])),
            new EnumSchema('confidence', 'Confiança na interpretação', ['low', 'medium', 'high']),
        ], ['customer_name', 'items', 'start_date', 'end_date', 'delivery', 'neighborhood', 'issues', 'confidence']);
    }
}
