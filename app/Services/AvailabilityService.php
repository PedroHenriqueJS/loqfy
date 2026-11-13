<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\Rental;
use App\Models\RentalItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AvailabilityService
{
    public function availableQuantity(Equipment $equipment, CarbonInterface $start, CarbonInterface $end): int
    {
        $held = RentalItem::query()
            ->where('equipment_id', $equipment->id)
            ->whereHas('rental', fn ($rental) => $rental
                ->whereIn('status', Rental::HOLDING)
                ->whereDate('start_date', '<=', $end->toDateString())
                ->whereDate('end_date', '>=', $start->toDateString()))
            ->sum('quantity');

        return max(0, $equipment->quantity_total - (int) $held);
    }

    public function alternatives(Equipment $equipment, CarbonInterface $start, CarbonInterface $end, int $limit = 3): Collection
    {
        return Equipment::query()
            ->where('category', $equipment->category)
            ->whereKeyNot($equipment->id)
            ->get()
            ->map(fn (Equipment $other) => [
                'equipment' => $other,
                'available_quantity' => $this->availableQuantity($other, $start, $end),
            ])
            ->filter(fn (array $row) => $row['available_quantity'] > 0)
            ->sortBy(fn (array $row) => abs($row['equipment']->daily_rate - $equipment->daily_rate))
            ->take($limit)
            ->values();
    }
}
