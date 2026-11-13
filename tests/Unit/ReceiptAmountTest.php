<?php

namespace Tests\Unit;

use App\Ai\ReceiptReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReceiptAmountTest extends TestCase
{
    #[DataProvider('totals')]
    public function test_printed_totals_become_numbers(?string $printed, ?float $expected): void
    {
        $this->assertSame($expected, ReceiptReader::amount($printed));
    }

    public static function totals(): array
    {
        return [
            'brazilian format' => ['R$ 1.234,56', 1234.56],
            'cents only' => ['89,90', 89.9],
            'dot as decimal' => ['1234.56', 1234.56],
            'thousands with dot, no cents' => ['1.250', 1250.0],
            'both separators, english style' => ['1,234.50', 1234.5],
            'whole number' => ['R$ 300', 300.0],
            'label around the value' => ['TOTAL R$ 47,00 PAGO', 47.0],
            'no number' => ['ilegível', null],
            'nothing read' => [null, null],
        ];
    }
}
