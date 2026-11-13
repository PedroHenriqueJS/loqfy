<?php

namespace App\Services;

use Carbon\CarbonInterface;

class PricingService
{
    public function days(CarbonInterface $start, CarbonInterface $end): int
    {
        return (int) $start->startOfDay()->diffInDays($end->startOfDay()) + 1;
    }

    public function lineTotal(float $dailyRate, int $quantity, int $days): float
    {
        return round($dailyRate * $quantity * $days, 2);
    }

    public function discountRate(int $days): float
    {
        $tiers = config('loqfy.length_discounts');
        krsort($tiers);

        foreach ($tiers as $minimumDays => $rate) {
            if ($days >= $minimumDays) {
                return $rate;
            }
        }

        return 0.0;
    }

    public function discount(float $subtotal, int $days): float
    {
        return round($subtotal * $this->discountRate($days), 2);
    }
}
