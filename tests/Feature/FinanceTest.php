<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\FinancialEntry;
use App\Models\Quote;
use App\Services\FinanceReport;
use App\Services\QuoteBuilder;
use App\Services\RentalService;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_a_quote_creates_a_receivable_due_on_the_first_day(): void
    {
        $this->seed(CatalogSeeder::class);

        $quote = app(QuoteBuilder::class)->build(new Quote, [
            'items' => [['equipment_id' => Equipment::where('name', 'Furadeira de impacto')->value('id'), 'quantity' => 2]],
            'start_date' => '2026-11-16',
            'end_date' => '2026-11-17',
            'delivery' => false,
            'customer_id' => Customer::create(['name' => 'Renata', 'document_verified_at' => now()])->id,
        ]);

        $rental = app(RentalService::class)->approve($quote);
        $entry = FinancialEntry::sole();

        $this->assertSame(FinancialEntry::INCOME, $entry->type);
        $this->assertSame(120.0, $entry->amount);
        $this->assertSame('2026-11-16', $entry->due_date->toDateString());
        $this->assertNull($entry->paid_at);
        $this->assertSame($rental->id, $entry->rental_id);
    }

    public function test_the_summary_separates_what_was_paid_from_what_is_due(): void
    {
        $today = CarbonImmutable::parse('2026-11-11');
        $entry = fn (string $type, float $amount, string $due, ?string $paid) => FinancialEntry::create([
            'type' => $type, 'category' => 'Test', 'description' => 'Test', 'amount' => $amount, 'due_date' => $due, 'paid_at' => $paid,
        ]);

        $entry('income', 1000, '2026-10-05', '2026-10-06');
        $entry('income', 400, '2026-11-03', '2026-11-03');
        $entry('expense', 250, '2026-11-05', '2026-11-05');
        $entry('income', 300, '2026-11-08', null);
        $entry('income', 200, '2026-11-20', null);
        $entry('expense', 500, '2026-12-05', null);
        $entry('expense', 900, '2027-02-01', null);

        $summary = app(FinanceReport::class)->summary($today);

        $this->assertSame(1150.0, $summary['balance']);
        $this->assertSame(400.0, $summary['income_this_month']);
        $this->assertSame(250.0, $summary['expense_this_month']);
        $this->assertSame(500.0, $summary['receivable_next_30_days']);
        $this->assertSame(500.0, $summary['payable_next_30_days']);
        $this->assertSame(1150.0, $summary['projected_balance_in_30_days']);
        $this->assertSame(300.0, $summary['overdue_receivable']);
        $this->assertSame(['month' => '2026-11', 'income' => 400.0, 'expense' => 250.0, 'balance' => 1150.0], end($summary['monthly']));
    }
}
