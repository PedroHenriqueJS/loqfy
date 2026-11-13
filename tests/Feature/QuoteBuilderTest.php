<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Quote;
use App\Models\RentalRequest;
use App\Services\QuoteBuilder;
use App\Services\RentalService;
use Carbon\CarbonImmutable;
use Database\Seeders\BookingsSeeder;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QuoteBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BookingsSeeder::$referenceDate = CarbonImmutable::parse('2026-11-11');
        $this->seed([CatalogSeeder::class, BookingsSeeder::class]);
    }

    private function build(array $data): array
    {
        $request = RentalRequest::create(['raw_text' => 'test']);
        $quote = app(QuoteBuilder::class)->build(new Quote(['rental_request_id' => $request->id, 'origin' => Quote::AI]), $data);

        return [$quote, $request->fresh()];
    }

    private function id(string $name): int
    {
        return Equipment::where('name', $name)->value('id');
    }

    public function test_prices_come_from_the_catalog_and_the_period_counts_both_days(): void
    {
        [$quote, $request] = $this->build([
            'items' => [['equipment_id' => $this->id('Escada extensível 7m'), 'quantity' => 2]],
            'start_date' => '2026-11-16',
            'end_date' => '2026-11-18',
            'delivery' => true,
            'neighborhood' => 'Kobrasol',
            'confidence' => 'high',
        ]);

        $this->assertSame(3, $quote->days);
        $this->assertSame(180.0, $quote->subtotal);
        $this->assertSame(40.0, $quote->delivery_fee);
        $this->assertSame(220.0, $quote->total);
        $this->assertSame([], $quote->issues);
        $this->assertSame(RentalRequest::READY, $request->status);
    }

    public function test_a_price_sent_by_the_model_is_ignored(): void
    {
        [$quote] = $this->build([
            'items' => [['equipment_id' => $this->id('Furadeira de impacto'), 'quantity' => 1, 'daily_rate' => 1, 'line_total' => 1]],
            'start_date' => '2026-11-12',
            'end_date' => '2026-11-12',
            'delivery' => false,
            'total' => 1,
        ]);

        $this->assertSame(30.0, $quote->items->first()->daily_rate);
        $this->assertSame(30.0, $quote->total);
    }

    public function test_a_week_or_more_gets_the_length_discount(): void
    {
        [$quote] = $this->build([
            'items' => [
                ['equipment_id' => $this->id('Escora metálica'), 'quantity' => 30],
                ['equipment_id' => $this->id('Vibrador de concreto'), 'quantity' => 1],
            ],
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-27',
            'delivery' => true,
            'neighborhood' => 'centro de Palhoça',
        ]);

        $this->assertSame(1080.0, $quote->subtotal);
        $this->assertSame(162.0, $quote->discount);
        $this->assertSame(988.0, $quote->total);
    }

    public function test_unavailable_stock_is_flagged_even_when_the_model_says_nothing(): void
    {
        [$quote, $request] = $this->build([
            'items' => [['equipment_id' => $this->id('Andaime tubular 1,0 x 1,5m'), 'quantity' => 20]],
            'start_date' => '2026-11-16',
            'end_date' => '2026-11-20',
            'delivery' => false,
            'issues' => [],
            'confidence' => 'high',
        ]);

        $this->assertFalse($quote->items->first()->available);
        $this->assertSame('unavailable', $quote->issues[0]['type']);
        $this->assertSame('system', $quote->issues[0]['source']);
        $this->assertSame(RentalRequest::NEEDS_REVIEW, $request->status);
    }

    public function test_unknown_equipment_id_and_missing_period_send_the_request_to_review(): void
    {
        [$quote, $request] = $this->build([
            'items' => [['equipment_id' => 9999, 'quantity' => 1, 'requested_term' => 'guindaste']],
            'start_date' => null,
            'end_date' => null,
            'delivery' => false,
        ]);

        $this->assertCount(0, $quote->items);
        $this->assertEqualsCanonicalizing(
            ['missing_info', 'missing_info', 'not_in_catalog'],
            array_column($quote->issues, 'type'),
        );
        $this->assertSame(RentalRequest::NEEDS_REVIEW, $request->status);
    }

    public function test_delivery_outside_the_zones_is_flagged_and_not_charged(): void
    {
        [$quote, $request] = $this->build([
            'items' => [['equipment_id' => $this->id('Furadeira de impacto'), 'quantity' => 2]],
            'start_date' => '2026-11-13',
            'end_date' => '2026-11-13',
            'delivery' => true,
            'neighborhood' => 'Blumenau',
        ]);

        $this->assertSame(0.0, $quote->delivery_fee);
        $this->assertSame('delivery_zone_unknown', $quote->issues[0]['type']);
        $this->assertSame(RentalRequest::NEEDS_REVIEW, $request->status);
    }

    public function test_approving_reserves_stock_and_a_second_quote_for_the_same_unit_is_refused(): void
    {
        $alisadora = ['equipment_id' => $this->id('Alisadora de concreto'), 'quantity' => 1];
        $customer = Customer::create(['name' => 'Renata', 'document_verified_at' => now()]);
        $data = ['items' => [$alisadora], 'start_date' => '2026-11-17', 'end_date' => '2026-11-18', 'delivery' => false, 'customer_id' => $customer->id];

        [$first] = $this->build($data);
        [$second] = $this->build($data);

        $rental = app(RentalService::class)->approve($first);
        $this->assertSame(320.0, $rental->total);
        $this->assertSame('approved', $first->fresh()->status);

        $this->expectException(ValidationException::class);
        app(RentalService::class)->approve($second);
    }

    public function test_a_manual_quote_needs_no_customer_message_and_can_be_recalculated(): void
    {
        $builder = app(QuoteBuilder::class);
        $data = [
            'items' => [['equipment_id' => $this->id('Furadeira de impacto'), 'quantity' => 1]],
            'start_date' => '2026-11-12',
            'end_date' => '2026-11-13',
            'delivery' => false,
            'customer_name' => 'Renata',
        ];

        $quote = $builder->build(new Quote, $data);

        $this->assertSame(Quote::MANUAL, $quote->origin);
        $this->assertNull($quote->rental_request_id);
        $this->assertSame('Renata', $quote->customer_name);
        $this->assertFalse($quote->needs_review);
        $this->assertSame(60.0, $quote->total);

        $data['items'][0]['quantity'] = 3;
        $again = $builder->build($quote, $data);

        $this->assertSame($quote->id, $again->id);
        $this->assertCount(1, $again->items);
        $this->assertSame(180.0, $again->total);
    }

    public function test_approval_needs_a_registered_customer_with_the_document_checked(): void
    {
        $data = [
            'items' => [['equipment_id' => $this->id('Furadeira de impacto'), 'quantity' => 1]],
            'start_date' => '2026-11-12',
            'end_date' => '2026-11-12',
            'delivery' => false,
            'customer_name' => 'Pessoa sem cadastro',
        ];

        [$unlinked] = $this->build($data);
        $this->assertNull($unlinked->customer_id);
        $this->assertApprovalRefused($unlinked, 'Vincule o orçamento a um cliente cadastrado');

        Customer::create(['name' => 'Marcos Antunes']);
        [$unverified] = $this->build(['customer_name' => 'marcos antunes'] + $data);
        $this->assertNotNull($unverified->customer_id);
        $this->assertApprovalRefused($unverified, 'Confira o documento de Marcos Antunes');

        Customer::where('name', 'Marcos Antunes')->update(['document_verified_at' => now()]);
        $rental = app(RentalService::class)->approve($unverified->fresh());
        $this->assertSame('Marcos Antunes', $rental->customer_name);
    }

    private function assertApprovalRefused(Quote $quote, string $message): void
    {
        try {
            app(RentalService::class)->approve($quote);
            $this->fail('The approval should have been refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($message, $e->errors()['quote'][0]);
        }
    }
}
