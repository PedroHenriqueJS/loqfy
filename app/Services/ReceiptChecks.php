<?php

namespace App\Services;

use App\Ai\ReceiptReader;
use App\Models\FinancialEntry;
use App\Support\Cnpj;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ReceiptChecks
{
    public function check(?array $read, array $earlierUses, CarbonInterface $today): array
    {
        $checks = [];

        foreach ($earlierUses as $use) {
            $checks[] = ['code' => 'duplicate_photo', 'message' => "Esta mesma foto já foi usada {$use}."];
        }

        if ($read === null) {
            return $checks;
        }

        $cnpj = Cnpj::digits($read['supplier_cnpj'] ?? null);
        if (strlen($cnpj) === 14 && ! Cnpj::isValid($cnpj)) {
            $checks[] = ['code' => 'invalid_cnpj', 'message' => 'O CNPJ lido no comprovante não passa na conferência dos dígitos. Pode ser erro de leitura ou comprovante alterado: confira na foto.'];
        }

        $date = is_string($read['date'] ?? null) ? CarbonImmutable::createFromFormat('!Y-m-d', $read['date']) : null;
        if ($date && $date->gt($today)) {
            $checks[] = ['code' => 'future_date', 'message' => "A data do comprovante ({$date->format('d/m/Y')}) é posterior a hoje."];
        }

        $total = $read['amount'] ?? null;
        $subtotal = ReceiptReader::amount($read['subtotal_as_printed'] ?? null);
        $discount = ReceiptReader::amount($read['discount_as_printed'] ?? null) ?? 0.0;
        if ($total !== null && $subtotal !== null && abs($subtotal - $discount - $total) > 0.01) {
            $checks[] = ['code' => 'totals_mismatch', 'message' => sprintf(
                'Subtotal menos desconto dá %s, mas o total impresso é %s. Confira os valores na foto.',
                $this->money($subtotal - $discount),
                $this->money($total),
            )];
        }

        if ($total !== null && $date) {
            $same = FinancialEntry::where('type', FinancialEntry::EXPENSE)
                ->whereDate('due_date', $date->toDateString())
                ->whereBetween('amount', [$total - 0.005, $total + 0.005])
                ->first();

            if ($same) {
                $checks[] = ['code' => 'duplicate_entry', 'message' => "Já existe uma despesa de {$this->money($total)} em {$date->format('d/m/Y')}: \"{$same->description}\". Veja se não é o mesmo comprovante."];
            }
        }

        return $checks;
    }

    private function money(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }
}
