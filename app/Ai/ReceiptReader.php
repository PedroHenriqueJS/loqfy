<?php

namespace App\Ai;

use App\Models\FinancialEntry;
use App\Support\Photo;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\RequestException;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\ValueObjects\Media\Image;
use Throwable;

class ReceiptReader
{
    public function read(string $photoPath, CarbonInterface $today): AgentResult
    {
        $startedAt = microtime(true);

        $response = Prism::structured()
            ->using(config('loqfy.ai.provider'), config('loqfy.ai.model'))
            ->usingTemperature(0)
            ->withMaxTokens(500)
            ->withClientRetry(4, fn (int $attempt, Throwable $e): int => 20_000, fn (Throwable $e): bool => $e instanceof RequestException && $e->response->status() === 429, throw: true)
            ->withSystemPrompt($this->prompt($today))
            ->withPrompt('Comprovante:', [Image::fromBase64(base64_encode(Photo::resizedJpeg($photoPath, 1280)), 'image/jpeg')])
            ->withSchema($this->schema())
            ->asStructured();

        $read = $response->structured ?: null;

        return new AgentResult(
            extraction: $read === null ? null : $this->suggestion($read),
            rawText: $response->text,
            toolCalls: [],
            steps: 1,
            promptTokens: $response->usage->promptTokens,
            completionTokens: $response->usage->completionTokens,
            latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
            error: $read === null ? 'The model returned an empty answer.' : null,
        );
    }

    public static function amount(?string $text): ?float
    {
        if ($text === null || ! preg_match('/\d[\d.,]*/', $text, $match)) {
            return null;
        }

        $digits = rtrim($match[0], '.,');
        $lastComma = strrpos($digits, ',');
        $lastDot = strrpos($digits, '.');

        $decimalAt = max($lastComma === false ? -1 : $lastComma, $lastDot === false ? -1 : $lastDot);
        $hasDecimals = $decimalAt >= 0 && strlen($digits) - $decimalAt - 1 <= 2;

        $whole = preg_replace('/\D/', '', $hasDecimals ? substr($digits, 0, $decimalAt) : $digits);
        $cents = $hasDecimals ? substr($digits, $decimalAt + 1) : '0';

        return (float) ($whole.'.'.$cents);
    }

    private function suggestion(array $read): array
    {
        $date = $read['date'] ?? null;
        $category = $read['category'] ?? null;

        return [
            'is_receipt' => (bool) ($read['is_receipt'] ?? false),
            'supplier' => $read['supplier'] ?? null,
            'description' => $read['description'] ?? null,
            'category' => in_array($category, FinancialEntry::CATEGORIES[FinancialEntry::EXPENSE], true) ? $category : 'Outras despesas',
            'date' => is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null,
            'supplier_cnpj' => $read['supplier_cnpj'] ?? null,
            'total_as_printed' => $read['total_as_printed'] ?? null,
            'subtotal_as_printed' => $read['subtotal_as_printed'] ?? null,
            'discount_as_printed' => $read['discount_as_printed'] ?? null,
            'amount' => self::amount($read['total_as_printed'] ?? null),
            'legible' => (bool) ($read['legible'] ?? true),
        ];
    }

    private function prompt(CarbonInterface $today): string
    {
        return <<<PROMPT
        Você lê a foto de um comprovante de despesa de uma locadora de máquinas e ferramentas: cupom de posto, recibo de oficina, nota de compra de peça ou conta. Um funcionário confere o que você ler antes de salvar.

        Hoje é {$today->toDateString()}.

        Regras:
        - Copie só o que está escrito na foto. Se um dado não aparece ou não dá para ler, use null. Não adivinhe.
        - total_as_printed é o valor total pago, copiado exatamente como está impresso, com os mesmos pontos e vírgulas. Não é o subtotal, o troco nem o valor de um item.
        - date é a data de emissão no formato AAAA-MM-DD. No Brasil a data vem como dia/mês/ano.
        - supplier é o nome do estabelecimento que emitiu.
        - supplier_cnpj é o CNPJ do estabelecimento, copiado como impresso. Null se não houver.
        - subtotal_as_printed é o total dos itens antes de descontos, e discount_as_printed é o desconto, ambos copiados como impressos. Use null quando o comprovante não mostrar a linha.
        - description é uma frase curta dizendo o que foi comprado ou pago, em português.
        - category: combustível, pedágio e frete são "Combustível e transporte"; conserto, peça de reposição, óleo e disco são "Manutenção"; máquina ou ferramenta nova é "Compra de equipamento"; luz, água, internet e aluguel são "Aluguel e contas"; guia de imposto é "Impostos". O que não couber é "Outras despesas".
        - is_receipt é false se a foto não for um comprovante de pagamento.
        - legible é false se a foto estiver escura, cortada ou borrada a ponto de comprometer a leitura.
        PROMPT;
    }

    private function schema(): ObjectSchema
    {
        return new ObjectSchema('receipt', 'Dados lidos do comprovante', [
            new BooleanSchema('is_receipt', 'true se a foto é um comprovante de pagamento'),
            new StringSchema('supplier', 'Nome do estabelecimento', nullable: true),
            new StringSchema('description', 'O que foi comprado ou pago', nullable: true),
            new EnumSchema('category', 'Categoria da despesa', array_values(array_diff(FinancialEntry::CATEGORIES[FinancialEntry::EXPENSE], ['Folha de pagamento']))),
            new StringSchema('date', 'Data de emissão, AAAA-MM-DD', nullable: true),
            new StringSchema('total_as_printed', 'Valor total, exatamente como impresso', nullable: true),
            new StringSchema('supplier_cnpj', 'CNPJ do estabelecimento, como impresso', nullable: true),
            new StringSchema('subtotal_as_printed', 'Total dos itens antes do desconto, como impresso', nullable: true),
            new StringSchema('discount_as_printed', 'Desconto, como impresso', nullable: true),
            new BooleanSchema('legible', 'false se a foto compromete a leitura'),
        ], ['is_receipt', 'supplier', 'description', 'category', 'date', 'total_as_printed', 'supplier_cnpj', 'subtotal_as_printed', 'discount_as_printed', 'legible']);
    }
}
