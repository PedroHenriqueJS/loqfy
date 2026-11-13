<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\FinancialEntry;
use App\Models\Quote;
use App\Models\Rental;
use App\Models\RentalItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DashboardReport
{
    public function __construct(private FinanceReport $finance, private PricingService $pricing) {}

    public function metrics(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $finance = $this->finance->summary($today);
        $rentals = Rental::with('items.equipment')->get();
        $equipment = Equipment::all();

        $holdingToday = $rentals->filter(fn (Rental $rental) => in_array($rental->status, Rental::HOLDING, true)
            && $rental->start_date->lte($today) && $rental->end_date->gte($today));
        $unitsOut = $holdingToday->sum(fn (Rental $rental) => $rental->items->sum('quantity'));
        $unitsTotal = (int) $equipment->sum('quantity_total');

        $dailyValueOut = $holdingToday->sum(fn (Rental $rental) => $rental->items->sum(fn (RentalItem $item) => $item->quantity * $item->daily_rate));
        $dailyValueTotal = $equipment->sum(fn (Equipment $item) => $item->quantity_total * $item->daily_rate);

        $out = $rentals->where('status', Rental::OUT);
        $overdue = $out->filter(fn (Rental $rental) => $rental->end_date->lt($today))->values();
        $upcoming = $out->filter(fn (Rental $rental) => $rental->end_date->gte($today) && $rental->end_date->lte($today->addDays(3)))->sortBy('end_date')->values();

        $revenue = FinancialEntry::where('type', FinancialEntry::INCOME)->where('category', '!=', 'Outras receitas')->whereNotNull('paid_at')->get();
        $between = fn (CarbonImmutable $from, CarbonImmutable $to): float => round($revenue->filter(fn (FinancialEntry $entry) => $entry->paid_at->gte($from) && $entry->paid_at->lte($to))->sum('amount'), 2);
        $lastMonthStart = $today->startOfMonth()->subMonth();

        $recentQuotes = Quote::where('created_at', '>=', $today->subDays(30))->get();

        return [
            'date' => $today->toDateString(),
            'kpis' => [
                'revenue_this_month_so_far' => $between($today->startOfMonth(), $today),
                'revenue_last_month_same_days' => $between($lastMonthStart, $lastMonthStart->addDays($today->day - 1)),
                'revenue_last_month_full' => $between($lastMonthStart, $lastMonthStart->endOfMonth()),
                'rentals_out' => $out->count(),
                'rentals_reserved' => $rentals->where('status', Rental::RESERVED)->count(),
                'units_out_today' => (int) $unitsOut,
                'units_total' => $unitsTotal,
                'utilization_percent' => $dailyValueTotal > 0 ? round(100 * $dailyValueOut / $dailyValueTotal, 1) : 0.0,
                'open_quotes' => Quote::where('status', 'draft')->count(),
                'quotes_needing_review' => Quote::where('status', 'draft')->where('needs_review', true)->count(),
                'overdue_returns' => $overdue->count(),
                'overdue_receivable' => $finance['overdue_receivable'],
                'cash_balance' => $finance['balance'],
            ],
            'revenue_by_month' => collect(range(5, 0))->map(fn (int $back) => $today->startOfMonth()->subMonths($back))->map(fn (CarbonImmutable $month) => [
                'month' => $month->format('Y-m'),
                'revenue' => $between($month, $month->endOfMonth()),
            ])->all(),
            'top_equipment_last_90_days' => $this->equipmentRevenue($rentals, $today)->take(6)->values()->all(),
            'idle_equipment_last_90_days' => $this->idleEquipment($equipment, $rentals, $today),
            'utilization_by_category' => $this->utilizationByCategory($equipment, $holdingToday),
            'upcoming_returns' => $upcoming->map(fn (Rental $rental) => $this->rentalLine($rental))->all(),
            'overdue_returns' => $overdue->map(fn (Rental $rental) => $this->rentalLine($rental))->all(),
            'overdue_receivables' => FinancialEntry::where('type', FinancialEntry::INCOME)->whereNull('paid_at')->whereDate('due_date', '<', $today)
                ->orderBy('due_date')->limit(5)->get()
                ->map(fn (FinancialEntry $entry) => ['description' => $entry->description, 'amount' => $entry->amount, 'due_date' => $entry->due_date->toDateString()])
                ->all(),
            'quotes_last_30_days' => [
                'total' => $recentQuotes->count(),
                'approved' => $recentQuotes->where('status', 'approved')->count(),
                'discarded' => $recentQuotes->where('status', 'discarded')->count(),
                'open' => $recentQuotes->where('status', 'draft')->count(),
                'drafted_by_ai' => $recentQuotes->where('origin', Quote::AI)->count(),
                'typed_by_hand' => $recentQuotes->where('origin', Quote::MANUAL)->count(),
            ],
            'finance' => [
                'income_this_month' => $finance['income_this_month'],
                'expense_this_month' => $finance['expense_this_month'],
                'receivable_next_30_days' => $finance['receivable_next_30_days'],
                'payable_next_30_days' => $finance['payable_next_30_days'],
                'projected_balance_in_30_days' => $finance['projected_balance_in_30_days'],
                'expenses_by_category_last_90_days' => $finance['expenses_by_category'],
            ],
        ];
    }

    private function equipmentRevenue(Collection $rentals, CarbonImmutable $today): Collection
    {
        return $rentals
            ->filter(fn (Rental $rental) => $rental->start_date->gte($today->subDays(90)) && $rental->start_date->lte($today))
            ->flatMap(fn (Rental $rental) => $rental->items->map(fn (RentalItem $item) => [
                'name' => $item->equipment->name,
                'revenue' => $this->pricing->lineTotal($item->daily_rate, $item->quantity, $this->pricing->days($rental->start_date, $rental->end_date)),
            ]))
            ->groupBy('name')
            ->map(fn (Collection $lines, string $name) => ['name' => $name, 'revenue' => round($lines->sum('revenue'), 2), 'rentals' => $lines->count()])
            ->sortByDesc('revenue');
    }

    private function idleEquipment(Collection $equipment, Collection $rentals, CarbonImmutable $today): array
    {
        $rented = $rentals
            ->filter(fn (Rental $rental) => $rental->end_date->gte($today->subDays(90)))
            ->flatMap(fn (Rental $rental) => $rental->items->pluck('equipment_id'))
            ->unique();

        return $equipment
            ->whereNotIn('id', $rented)
            ->sortByDesc('daily_rate')
            ->take(5)
            ->map(fn (Equipment $item) => ['name' => $item->name, 'units' => $item->quantity_total, 'daily_rate' => $item->daily_rate])
            ->values()
            ->all();
    }

    private function utilizationByCategory(Collection $equipment, Collection $holdingToday): array
    {
        $outByCategory = $holdingToday
            ->flatMap(fn (Rental $rental) => $rental->items)
            ->groupBy(fn (RentalItem $item) => $item->equipment->category)
            ->map(fn (Collection $items) => (int) $items->sum('quantity'));

        return $equipment
            ->groupBy('category')
            ->map(function (Collection $items, string $category) use ($outByCategory) {
                $total = (int) $items->sum('quantity_total');
                $out = $outByCategory[$category] ?? 0;

                return ['category' => $category, 'units_out' => $out, 'units_total' => $total, 'percent' => $total > 0 ? round(100 * $out / $total, 1) : 0.0];
            })
            ->sortByDesc('percent')
            ->values()
            ->all();
    }

    private function rentalLine(Rental $rental): array
    {
        return [
            'rental_id' => $rental->id,
            'customer' => $rental->customer_name,
            'end_date' => $rental->end_date->toDateString(),
            'items' => $rental->items->map(fn (RentalItem $item) => "{$item->quantity} × {$item->equipment->name}")->implode(', '),
        ];
    }
}
