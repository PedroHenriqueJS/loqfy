<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeInspection;
use App\Jobs\ProcessRentalRequest;
use App\Models\AiRun;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Quote;
use App\Models\RentalItem;
use App\Models\RentalRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\BookingsSeeder;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Structured\Request as StructuredRequest;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\Testing\TextStepFake;
use Prism\Prism\Text\Request as TextRequest;
use Prism\Prism\ValueObjects\ToolResult;
use Prism\Prism\ValueObjects\Usage;
use Tests\TestCase;

class AiFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-11-11 10:00');
        BookingsSeeder::$referenceDate = CarbonImmutable::today();
        $this->seed([CatalogSeeder::class, BookingsSeeder::class]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function drill(): Equipment
    {
        return Equipment::where('name', 'Furadeira de impacto')->firstOrFail();
    }

    private function lookup(array $toolResults): TextResponseFake
    {
        return TextResponseFake::make()
            ->withSteps(collect([TextStepFake::make()->withToolResults($toolResults)]))
            ->withUsage(new Usage(1000, 50));
    }

    public function test_a_message_becomes_a_quote_priced_from_the_catalog(): void
    {
        $drill = $this->drill();
        $searchResult = json_encode(['term' => 'furadeira', 'matches' => [['equipment_id' => $drill->id, 'name' => $drill->name, 'available' => true]]]);

        $fake = Prism::fake([
            $this->lookup([new ToolResult('call_1', 'search_equipment', ['term' => 'furadeira', 'quantity' => 2], $searchResult)]),
            StructuredResponseFake::make()->withStructured([
                'customer_name' => 'Renata',
                'items' => [['requested_term' => 'furadeira', 'equipment_id' => $drill->id, 'quantity' => 2, 'substitutes_equipment_id' => null]],
                'start_date' => '2026-11-12',
                'end_date' => '2026-11-13',
                'delivery' => false,
                'neighborhood' => null,
                'issues' => [],
                'confidence' => 'high',
            ])->withUsage(new Usage(800, 120)),
        ]);

        $request = RentalRequest::create(['raw_text' => 'preciso de 2 furadeiras amanhã e sexta, retiro']);
        ProcessRentalRequest::dispatchSync($request);

        $quote = Quote::where('rental_request_id', $request->id)->sole();
        $this->assertSame(Quote::AI, $quote->origin);
        $this->assertSame(120.0, $quote->total);
        $this->assertSame(RentalRequest::READY, $request->fresh()->status);

        $run = AiRun::where('feature', 'quote')->sole();
        $this->assertTrue($run->success);
        $this->assertSame('search_equipment', $run->tool_calls[0]['name']);
        $this->assertSame(1800 + 170, $run->prompt_tokens + $run->completion_tokens);

        $fake->assertRequest(function (array $requests) use ($drill) {
            $this->assertInstanceOf(TextRequest::class, $requests[0]);
            $this->assertInstanceOf(StructuredRequest::class, $requests[1]);
            $this->assertStringContainsString($drill->name, $requests[1]->prompt());
        });
    }

    public function test_an_unreadable_answer_still_leaves_a_draft_for_a_person(): void
    {
        Prism::fake([$this->lookup([]), StructuredResponseFake::make()->withStructured([])]);

        $request = RentalRequest::create(['raw_text' => 'oi']);
        ProcessRentalRequest::dispatchSync($request);

        $quote = Quote::where('rental_request_id', $request->id)->sole();
        $this->assertTrue($quote->needs_review);
        $this->assertContains('missing_info', array_column($quote->issues, 'type'));
        $this->assertFalse(AiRun::where('feature', 'quote')->sole()->success);
    }

    public function test_a_receipt_is_read_into_a_suggestion_with_the_total_parsed_by_code(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        Prism::fake([StructuredResponseFake::make()->withStructured([
            'is_receipt' => true,
            'supplier' => 'FERRAGENS CONTINENTE',
            'description' => 'Compra de furadeiras',
            'category' => 'Compra de equipamento',
            'date' => '2026-11-09',
            'total_as_printed' => 'R$ 1.220,27',
            'supplier_cnpj' => '83.977.706/0001-35',
            'subtotal_as_printed' => '1.284,50',
            'discount_as_printed' => '-64,23',
            'legible' => true,
        ])]);

        $photo = new UploadedFile(base_path('exemplos/comprovantes/03-pedido-ferragens.jpg'), 'nota.jpg', 'image/jpeg', null, true);

        $this->post('/api/finance/receipts', ['photo' => $photo], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('suggestion.amount', 1220.27)
            ->assertJsonPath('suggestion.category', 'Compra de equipamento')
            ->assertJsonPath('checks', []);

        $this->assertSame(1, AiRun::where('feature', 'receipt')->count());
    }

    public function test_a_provider_outage_is_retried_and_then_reported_without_a_crash(): void
    {
        Storage::fake('public');
        Sleep::fake();
        Http::fake(['api.groq.com/*' => Http::response(['error' => ['type' => 'internal_server_error', 'message' => 'over capacity']], 503)]);
        $this->actingAs(User::factory()->create());

        $photo = new UploadedFile(base_path('exemplos/comprovantes/01-cupom-posto.jpg'), 'cupom.jpg', 'image/jpeg', null, true);

        $this->post('/api/finance/receipts', ['photo' => $photo], ['Accept' => 'application/json'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'O comprovante não pôde ser lido agora. Tente de novo em um minuto ou preencha à mão.');

        Http::assertSentCount(4);
    }

    public function test_an_inspection_is_analysed_and_waits_for_a_person(): void
    {
        Storage::fake('public');
        $item = RentalItem::first();
        $inspection = Inspection::create([
            'rental_item_id' => $item->id,
            'checkout_photo' => UploadedFile::fake()->image('saida.jpg', 640, 480)->store('inspections', 'public'),
            'return_photo' => UploadedFile::fake()->image('volta.jpg', 640, 480)->store('inspections', 'public'),
            'status' => Inspection::PENDING,
        ]);

        Prism::fake([StructuredResponseFake::make()->withStructured([
            'same_equipment' => true,
            'damages' => [['location' => 'Lateral', 'description' => 'Amassado novo', 'severity' => 'moderate', 'confidence' => 'medium']],
            'photo_quality' => 'ok',
            'summary' => 'Um amassado novo na lateral.',
        ])]);

        AnalyzeInspection::dispatchSync($inspection);

        $inspection->refresh();
        $this->assertSame(Inspection::ANALYZED, $inspection->status);
        $this->assertSame('Amassado novo', $inspection->ai_result['damages'][0]['description']);
        $this->assertNull($inspection->decision);
    }

    public function test_the_dashboard_analysis_flags_numbers_that_are_not_in_the_data(): void
    {
        $this->actingAs(User::factory()->create());

        Prism::fake([StructuredResponseFake::make()->withStructured([
            'headline' => 'Semana tranquila.',
            'insights' => [
                ['kind' => 'risk', 'title' => 'Atraso', 'evidence' => 'Há 1 devolução atrasada e R$ 99.999,00 a receber.', 'action' => 'Ligar para o cliente.'],
            ],
        ])]);

        $this->postJson('/api/dashboard/analysis')
            ->assertOk()
            ->assertJsonPath('analysis.headline', 'Semana tranquila.')
            ->assertJsonPath('analysis.insights.0.unverified_numbers', ['99.999,00']);
    }
}
