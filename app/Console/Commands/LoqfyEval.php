<?php

namespace App\Console\Commands;

use App\Ai\InspectionAnalyzer;
use App\Ai\QuoteAgent;
use App\Ai\ReceiptReader;
use App\Models\Quote;
use App\Models\RentalRequest;
use App\Services\QuoteBuilder;
use Carbon\CarbonImmutable;
use Database\Seeders\BookingsSeeder;
use Database\Seeders\CatalogSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class LoqfyEval extends Command
{
    protected $signature = 'loqfy:eval {suite : quote, inspection or receipt}
        {--only= : Comma-separated case ids}
        {--pause= : Seconds between cases, to stay under the provider token limit (default 20 for quote, 35 for inspection, 20 for receipt)}';

    protected $description = 'Run an evaluation suite against the AI features and report accuracy';

    public function handle(): int
    {
        return match ($this->argument('suite')) {
            'quote' => $this->quote(),
            'inspection' => $this->inspection(),
            'receipt' => $this->receipt(),
            default => $this->fail("Unknown suite \"{$this->argument('suite')}\"."),
        };
    }

    private function quote(): int
    {
        $suite = json_decode(file_get_contents(base_path('evals/quote.json')), true, flags: JSON_THROW_ON_ERROR);
        $today = CarbonImmutable::createFromFormat('!Y-m-d', $suite['reference_date']);

        $cases = collect($suite['cases']);
        if ($only = $this->option('only')) {
            $cases = $cases->whereIn('id', explode(',', $only))->values();
        }

        $this->useEvalDatabase($today);
        $this->info("Quote eval: {$cases->count()} cases, model ".config('loqfy.ai.model').", reference date {$today->toDateString()}");

        $agent = app(QuoteAgent::class);
        $builder = app(QuoteBuilder::class);
        $results = [];

        foreach ($cases as $index => $case) {
            if ($index > 0) {
                sleep((int) ($this->option('pause') ?? 20));
            }

            $results[] = $result = $this->runQuoteCase($case, $today, $agent, $builder);
            $this->line(sprintf(
                '  %s %-14s %s',
                $result['passed'] ? '<info>PASS</info>' : '<error>FAIL</error>',
                $case['id'],
                $result['passed'] ? '' : implode('; ', $result['failures']),
            ));
        }

        $this->reportQuote($results, $suite['reference_date']);

        return self::SUCCESS;
    }

    private function receipt(): int
    {
        $path = base_path('evals/receipt/cases.json');
        if (! file_exists($path)) {
            return $this->fail('evals/receipt/cases.json not found. See evals/receipt/README.md.');
        }

        $suite = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $today = CarbonImmutable::createFromFormat('!Y-m-d', $suite['reference_date']);
        $cases = collect($suite['cases']);
        if ($only = $this->option('only')) {
            $cases = $cases->whereIn('id', explode(',', $only))->values();
        }

        $this->info("Receipt eval: {$cases->count()} photos, model ".config('loqfy.ai.model'));
        $reader = app(ReceiptReader::class);
        $results = collect();

        foreach ($cases as $index => $case) {
            if ($index > 0) {
                sleep((int) ($this->option('pause') ?? 20));
            }

            $result = [
                'id' => $case['id'],
                'category' => 'receipt',
                'text' => $case['note'] ?? $case['photo'],
                'checks' => ['valid_output' => false, 'total' => false, 'date' => false, 'category' => false],
                'failures' => [],
                'latency_ms' => 0,
                'tokens' => 0,
                'actual' => null,
            ];

            try {
                $run = $reader->read(base_path('evals/receipt/'.$case['photo']), $today);
                $result['latency_ms'] = $run->latencyMs;
                $result['tokens'] = $run->promptTokens + $run->completionTokens;
                $read = $result['actual'] = $run->extraction;

                if ($read === null) {
                    $result['failures'][] = 'invalid output: '.$run->error;
                } else {
                    $result['checks'] = [
                        'valid_output' => true,
                        'total' => $read['amount'] !== null && abs($read['amount'] - $case['total']) < 0.005,
                        'date' => $read['date'] === $case['date'],
                        'category' => $read['category'] === $case['category'],
                    ];

                    foreach (['total' => 'amount', 'date' => 'date', 'category' => 'category'] as $check => $field) {
                        if (! $result['checks'][$check]) {
                            $result['failures'][] = "{$check}: got ".json_encode($read[$field], JSON_UNESCAPED_UNICODE);
                        }
                    }
                }
            } catch (Throwable $e) {
                $result['failures'][] = 'error: '.$e->getMessage();
            }

            $result['passed'] = $result['failures'] === [];
            $results->push($result);

            $this->line(sprintf('  %s %-14s %s', $result['passed'] ? '<info>PASS</info>' : '<error>FAIL</error>', $case['id'], implode('; ', $result['failures'])));
        }

        $rate = fn (string $check) => $this->ratio($results->where("checks.{$check}", true)->count(), $results->count());
        $summary = [
            'cases_fully_correct' => $this->ratio($results->where('passed', true)->count(), $results->count()),
            'total_correct' => $rate('total'),
            'date_correct' => $rate('date'),
            'category_correct' => $rate('category'),
            'valid_output' => $rate('valid_output'),
            'average_latency_ms' => (int) round($results->avg('latency_ms')),
            'average_tokens' => (int) round($results->avg('tokens')),
        ];

        $this->newLine();
        $this->table(['Metric', 'Result'], collect($summary)->map(fn ($value, $name) => [str_replace('_', ' ', $name), $value])->values()->all());
        $this->save('receipt', ['reference_date' => $suite['reference_date'], 'summary' => $summary, 'cases' => $results->all()]);

        return self::SUCCESS;
    }

    private function inspection(): int
    {
        $path = base_path('evals/inspection/cases.json');
        if (! file_exists($path)) {
            return $this->fail('evals/inspection/cases.json not found. See evals/inspection/README.md.');
        }

        $suite = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $cases = collect($suite['cases']);
        if ($only = $this->option('only')) {
            $cases = $cases->whereIn('id', explode(',', $only))->values();
        }

        $this->info("Inspection eval: {$cases->count()} photo pairs, model ".config('loqfy.ai.model'));
        $analyzer = app(InspectionAnalyzer::class);
        $results = collect();

        foreach ($cases as $index => $case) {
            if ($index > 0) {
                sleep((int) ($this->option('pause') ?? 35));
            }

            $result = [
                'id' => $case['id'],
                'category' => $case['has_damage'] ? 'damaged' : 'not damaged',
                'text' => $case['equipment'].': '.($case['note'] ?? ''),
                'expected_damage' => $case['has_damage'],
                'flagged_damage' => null,
                'failures' => [],
                'latency_ms' => 0,
                'tokens' => 0,
                'actual' => null,
            ];

            try {
                $run = $analyzer->analyze(
                    base_path('evals/inspection/'.$case['checkout']),
                    base_path('evals/inspection/'.$case['return']),
                    $case['equipment'],
                );
                $result['latency_ms'] = $run->latencyMs;
                $result['tokens'] = $run->promptTokens + $run->completionTokens;
                $result['actual'] = $run->extraction;

                if ($run->extraction === null) {
                    $result['failures'][] = 'invalid output: '.$run->error;
                } else {
                    $result['flagged_damage'] = count($run->extraction['damages'] ?? []) > 0;

                    if ($result['flagged_damage'] !== $case['has_damage']) {
                        $result['failures'][] = $case['has_damage']
                            ? 'missed the damage: '.($run->extraction['summary'] ?? '')
                            : 'false alarm: '.collect($run->extraction['damages'])->pluck('description')->implode(' / ');
                    }

                    if (($run->extraction['same_equipment'] ?? true) === false) {
                        $result['failures'][] = 'said the photos show different equipment';
                    }
                }
            } catch (Throwable $e) {
                $result['failures'][] = 'error: '.$e->getMessage();
            }

            $result['passed'] = $result['failures'] === [];
            $results->push($result);

            $this->line(sprintf('  %s %-14s %s', $result['passed'] ? '<info>PASS</info>' : '<error>FAIL</error>', $case['id'], implode('; ', $result['failures'])));
        }

        $damaged = $results->where('expected_damage', true);
        $clean = $results->where('expected_damage', false);

        $summary = [
            'cases_fully_correct' => $this->ratio($results->where('passed', true)->count(), $results->count()),
            'damage_detected' => $this->ratio($damaged->where('flagged_damage', true)->count(), $damaged->count()),
            'false_alarms' => $this->ratio($clean->where('flagged_damage', true)->count(), $clean->count()),
            'valid_output' => $this->ratio($results->whereNotNull('flagged_damage')->count(), $results->count()),
            'average_latency_ms' => (int) round($results->avg('latency_ms')),
            'average_tokens' => (int) round($results->avg('tokens')),
        ];

        $this->newLine();
        $this->table(['Metric', 'Result'], collect($summary)->map(fn ($value, $name) => [str_replace('_', ' ', $name), $value])->values()->all());
        $this->save('inspection', ['summary' => $summary, 'cases' => $results->all()]);

        return self::SUCCESS;
    }

    private function runQuoteCase(array $case, CarbonImmutable $today, QuoteAgent $agent, QuoteBuilder $builder): array
    {
        $result = [
            'id' => $case['id'],
            'category' => $case['category'],
            'text' => $case['text'],
            'checks' => ['valid_output' => false, 'items' => false, 'dates' => false, 'delivery' => false, 'review_flag' => false, 'total' => null],
            'failures' => [],
            'latency_ms' => 0,
            'tokens' => 0,
            'actual' => null,
        ];

        try {
            $run = $agent->run($case['text'], $today);
            $result['latency_ms'] = $run->latencyMs;
            $result['tokens'] = $run->promptTokens + $run->completionTokens;

            if ($run->extraction === null) {
                $result['failures'][] = 'invalid output: '.$run->error;
                $result['passed'] = false;

                return $result;
            }

            $request = RentalRequest::create(['raw_text' => $case['text'], 'created_at' => $today]);
            $quote = $builder->build(new Quote(['rental_request_id' => $request->id, 'origin' => Quote::AI]), $run->extraction);
        } catch (Throwable $e) {
            $result['failures'][] = 'error: '.$e->getMessage();
            $result['passed'] = false;

            return $result;
        }

        $expect = $case['expect'];
        $actualItems = $quote->items->map(fn ($item) => ['equipment' => $item->equipment->name, 'quantity' => $item->quantity])->all();
        $needsReview = $quote->needs_review;

        $result['actual'] = [
            'items' => $actualItems,
            'start_date' => $quote->start_date?->toDateString(),
            'end_date' => $quote->end_date?->toDateString(),
            'zone' => $quote->delivery_zone_id,
            'needs_review' => $needsReview,
            'total' => $quote->total,
            'issues' => $quote->issues,
        ];

        $checks = [
            'valid_output' => true,
            'items' => $this->itemsMatch($expect['items'], $actualItems),
            'dates' => $result['actual']['start_date'] === $expect['start_date'] && $result['actual']['end_date'] === $expect['end_date'],
            'delivery' => $quote->delivery_zone_id === $expect['zone'],
            'review_flag' => $needsReview === $expect['needs_review'],
            'total' => array_key_exists('total', $expect) ? abs($quote->total - $expect['total']) < 0.005 : null,
        ];

        $result['checks'] = $checks;
        $result['invented_price'] = $this->hasInventedPrice($quote);

        foreach ($checks as $name => $ok) {
            if ($ok === false) {
                $result['failures'][] = match ($name) {
                    'items' => 'items: got '.json_encode($actualItems, JSON_UNESCAPED_UNICODE),
                    'dates' => "dates: got {$result['actual']['start_date']}..{$result['actual']['end_date']}",
                    'delivery' => 'delivery zone: got '.json_encode($quote->delivery_zone_id),
                    'review_flag' => 'review flag: got '.json_encode($needsReview),
                    'total' => "total: got {$quote->total}",
                    default => $name,
                };
            }
        }

        $result['passed'] = $result['failures'] === [];

        return $result;
    }

    private function itemsMatch(array $expected, array $actual): bool
    {
        if (count($expected) !== count($actual)) {
            return false;
        }

        foreach ($expected as $want) {
            $found = null;

            foreach ($actual as $key => $have) {
                if (in_array($have['equipment'], $want['equipment'], true) && $have['quantity'] === $want['quantity']) {
                    $found = $key;
                    break;
                }
            }

            if ($found === null) {
                return false;
            }

            unset($actual[$found]);
        }

        return true;
    }

    private function hasInventedPrice(Quote $quote): bool
    {
        return $quote->items->contains(fn ($item) => abs($item->daily_rate - $item->equipment->daily_rate) > 0.005);
    }

    private function reportQuote(array $results, string $referenceDate): void
    {
        $results = collect($results);
        $total = $results->count();
        $rate = fn (string $check) => $this->ratio($results->where("checks.{$check}", true)->count(), $results->where("checks.{$check}", '!==', null)->count());

        $this->newLine();
        $this->table(['Category', 'Passed'], $results->groupBy('category')->map(fn ($group, $category) => [
            $category, $this->ratio($group->where('passed', true)->count(), $group->count()),
        ])->values()->all());

        $summary = [
            'cases_fully_correct' => $this->ratio($results->where('passed', true)->count(), $total),
            'valid_output' => $rate('valid_output'),
            'items_correct' => $rate('items'),
            'dates_correct' => $rate('dates'),
            'delivery_zone_correct' => $rate('delivery'),
            'review_flag_correct' => $rate('review_flag'),
            'total_correct_when_checked' => $rate('total'),
            'invented_prices' => $results->where('invented_price', true)->count(),
            'average_latency_ms' => (int) round($results->avg('latency_ms')),
            'average_tokens' => (int) round($results->avg('tokens')),
        ];

        $this->table(['Metric', 'Result'], collect($summary)->map(fn ($value, $name) => [str_replace('_', ' ', $name), $value])->values()->all());

        $this->save('quote', ['reference_date' => $referenceDate, 'summary' => $summary, 'cases' => $results->all()]);
    }

    private function save(string $suite, array $report): void
    {
        $json = json_encode(
            ['suite' => $suite, 'model' => config('loqfy.ai.model'), 'ran_at' => now()->toIso8601String()] + $report,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        file_put_contents(base_path("evals/results/{$suite}-latest.json"), $json);
        file_put_contents(base_path("evals/results/{$suite}-".now()->format('Ymd-His').'.json'), $json);
        $this->line("Saved to evals/results/{$suite}-latest.json");
    }

    private function ratio(int $part, int $whole): string
    {
        return $whole === 0 ? 'n/a' : sprintf('%d/%d (%d%%)', $part, $whole, round(100 * $part / $whole));
    }

    private function useEvalDatabase(CarbonImmutable $today): void
    {
        $path = database_path('eval.sqlite');
        if (! file_exists($path)) {
            touch($path);
        }

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $path]);
        DB::purge('sqlite');

        Artisan::call('migrate:fresh', ['--force' => true]);

        BookingsSeeder::$referenceDate = $today;
        (new CatalogSeeder)->run();
        (new BookingsSeeder)->run();
    }
}
