<?php

namespace App\Services;

use App\Models\FinancialEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class FinanceReport
{
    public function summary(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $entries = FinancialEntry::all();
        $paid = $entries->whereNotNull('paid_at');
        $open = $entries->whereNull('paid_at');

        $monthStart = $today->startOfMonth();
        $paidThisMonth = $paid->filter(fn (FinancialEntry $entry) => $entry->paid_at->gte($monthStart) && $entry->paid_at->lte($today));
        $horizon = $today->addDays(30);
        $dueSoon = $open->filter(fn (FinancialEntry $entry) => $entry->due_date->lte($horizon));

        $balance = round($paid->sum(fn (FinancialEntry $entry) => $entry->signedAmount()), 2);
        $receivable = $this->total($dueSoon, FinancialEntry::INCOME);
        $payable = $this->total($dueSoon, FinancialEntry::EXPENSE);

        return [
            'balance' => $balance,
            'income_this_month' => $this->total($paidThisMonth, FinancialEntry::INCOME),
            'expense_this_month' => $this->total($paidThisMonth, FinancialEntry::EXPENSE),
            'receivable_next_30_days' => $receivable,
            'payable_next_30_days' => $payable,
            'projected_balance_in_30_days' => round($balance + $receivable - $payable, 2),
            'overdue_receivable' => $this->total($open->filter(fn (FinancialEntry $entry) => $entry->due_date->lt($today)), FinancialEntry::INCOME),
            'monthly' => $this->monthly($paid, $today),
            'expenses_by_category' => $this->expensesByCategory($paid, $today),
        ];
    }

    private function monthly(Collection $paid, CarbonImmutable $today): array
    {
        $first = $today->startOfMonth()->subMonths(5);
        $balance = round($paid->filter(fn (FinancialEntry $entry) => $entry->paid_at->lt($first))->sum(fn (FinancialEntry $entry) => $entry->signedAmount()), 2);
        $months = [];

        for ($month = $first; $month->lte($today); $month = $month->addMonth()) {
            $inMonth = $paid->filter(fn (FinancialEntry $entry) => $entry->paid_at->format('Y-m') === $month->format('Y-m'));
            $income = $this->total($inMonth, FinancialEntry::INCOME);
            $expense = $this->total($inMonth, FinancialEntry::EXPENSE);
            $balance = round($balance + $income - $expense, 2);

            $months[] = ['month' => $month->format('Y-m'), 'income' => $income, 'expense' => $expense, 'balance' => $balance];
        }

        return $months;
    }

    private function expensesByCategory(Collection $paid, CarbonImmutable $today): array
    {
        return $paid
            ->where('type', FinancialEntry::EXPENSE)
            ->filter(fn (FinancialEntry $entry) => $entry->paid_at->gte($today->subDays(90)))
            ->groupBy('category')
            ->map(fn (Collection $group, string $category) => ['category' => $category, 'amount' => round($group->sum('amount'), 2)])
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    private function total(Collection $entries, string $type): float
    {
        return round($entries->where('type', $type)->sum('amount'), 2);
    }
}
