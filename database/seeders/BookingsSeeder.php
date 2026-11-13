<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\FinancialEntry;
use App\Models\Rental;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class BookingsSeeder extends Seeder
{
    public static ?CarbonImmutable $referenceDate = null;

    public function run(): void
    {
        $today = self::$referenceDate ?? CarbonImmutable::today();
        $saturday = $today->next(CarbonImmutable::SATURDAY);

        $bookings = [
            ['Rolo compactador 1,5t', 1, $today->subDay(), $today->addDays(9), 'Pavimentadora Ilha Sul'],
            ['Betoneira 400L', 4, $saturday, $saturday->addDay(), 'Construtora Morro das Pedras'],
            ['Gerador 12 kVA', 1, $today->addDays(5), $today->addDays(7), 'Eventos Beira-Mar'],
            ['Andaime tubular 1,0 x 1,5m', 50, $today->addDay(), $today->addDays(19), 'Pinturas Continente'],
            ['Martelete rompedor 10kg', 2, $today, $today->addDays(3), 'Reformas Kobrasol'],
        ];

        foreach ($bookings as [$name, $quantity, $start, $end, $customer]) {
            $equipment = Equipment::where('name', $name)->firstOrFail();

            $rental = Rental::create([
                'customer_id' => Customer::firstOrCreate(['name' => $customer], ['kind' => DemoHistorySeeder::kindOf($customer), 'document_verified_at' => $today->subMonths(3)])->id,
                'customer_name' => $customer,
                'start_date' => $start,
                'end_date' => $end,
                'status' => $start->lte($today) ? Rental::OUT : Rental::RESERVED,
                'total' => $equipment->daily_rate * $quantity * ($start->diffInDays($end) + 1),
            ]);

            $rental->items()->create([
                'equipment_id' => $equipment->id,
                'quantity' => $quantity,
                'daily_rate' => $equipment->daily_rate,
            ]);

            FinancialEntry::create([
                'type' => FinancialEntry::INCOME,
                'category' => FinancialEntry::RENTAL_CATEGORY,
                'description' => "Locação nº {$rental->id}, {$customer}",
                'amount' => $rental->total,
                'due_date' => $start,
                'paid_at' => $start->lt($today) ? $start : null,
                'rental_id' => $rental->id,
            ]);
        }
    }
}
