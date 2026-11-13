<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\FinancialEntry;
use App\Models\Rental;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DemoHistorySeeder extends Seeder
{
    private const WEEKS = 22;

    private const POPULAR = [
        'Betoneira 400L' => [1, 2], 'Betoneira 150L' => [1, 1], 'Andaime tubular 1,0 x 1,5m' => [8, 30],
        'Escora metálica' => [20, 80], 'Furadeira de impacto' => [1, 2], 'Martelete rompedor 10kg' => [1, 2],
        'Serra mármore' => [1, 2], 'Esmerilhadeira 7 polegadas' => [1, 2], 'Lavadora de alta pressão' => [1, 1],
        'Carrinho de mão' => [2, 6], 'Placa vibratória' => [1, 1], 'Compactador de solo tipo sapo' => [1, 1],
        'Gerador 5 kVA' => [1, 1], 'Escada extensível 7m' => [1, 2], 'Vibrador de concreto' => [1, 1],
        'Martelo demolidor 30kg' => [1, 1], 'Serra circular' => [1, 1], 'Compressor de ar 10 pés' => [1, 1],
    ];

    private const OCCASIONAL = [
        'Rolo compactador 1,5t' => [1, 1], 'Cortadora de piso' => [1, 1], 'Gerador 12 kVA' => [1, 1],
        'Alisadora de concreto' => [1, 1], 'Bomba submersível' => [1, 1], 'Aspirador industrial' => [1, 1], 'Policorte' => [1, 1],
    ];

    private const CUSTOMERS = [
        'Construtora Ilha Norte', 'Reformas Kobrasol', 'Pinturas Continente', 'Marcenaria Trindade', 'Engenharia Costa Sul',
        'João Pedreira', 'Marcos Antunes', 'Renata Souza', 'Obras Campeche', 'Telhados Biguaçu', 'Carla Menezes', 'Empreiteira Pedra Branca',
    ];

    public function run(): void
    {
        mt_srand(20261111);

        $today = CarbonImmutable::today();
        $equipment = Equipment::all()->keyBy('name');
        $firstDay = $today->subWeeks(self::WEEKS)->startOfWeek();
        $customers = collect(self::CUSTOMERS)->mapWithKeys(fn (string $name) => [$name => Customer::firstOrCreate(['name' => $name], ['phone' => $this->phone(), 'kind' => self::kindOf($name),
            'document_verified_at' => $name === 'Renata Souza' ? null : $firstDay])]);

        FinancialEntry::create([
            'type' => FinancialEntry::INCOME, 'category' => 'Outras receitas', 'description' => 'Saldo inicial',
            'amount' => 12000, 'due_date' => $firstDay->subMonths(2), 'paid_at' => $firstDay->subMonths(2),
        ]);

        for ($week = 0; $week < self::WEEKS; $week++) {
            $orders = (int) round(mt_rand(8, 10) * (0.75 + 0.25 * $week / self::WEEKS));
            $recent = $week >= self::WEEKS - 2;

            for ($order = 0; $order < $orders; $order++) {
                $items = $this->pickItems($equipment);
                $start = $firstDay->addWeeks($week)->addDays(mt_rand(0, 5));

                $heavy = collect($items)->contains(fn (array $item) => $item[0]->daily_rate >= 250);
                $end = $start->addDays($recent || $heavy || mt_rand(1, 100) <= 70 ? mt_rand(0, 3) : mt_rand(4, 12));

                if ($end->gte($today->subDay())) {
                    continue;
                }

                $this->rental($customers->random(), $items, $start, $end, Rental::RETURNED, $today);
            }
        }

        $this->rental($customers['Construtora Ilha Norte'], [[$equipment['Betoneira 150L'], 2]], $today->subDays(4), $today->subDay(), Rental::OUT, $today);
        $this->rental($customers['João Pedreira'], [[$equipment['Serra circular'], 1], [$equipment['Esmerilhadeira 7 polegadas'], 1]], $today->subDay(), $today, Rental::OUT, $today);
        $this->rental($customers['Marcenaria Trindade'], [[$equipment['Compressor de ar 10 pés'], 1]], $today->subDays(2), $today->addDay(), Rental::OUT, $today);

        $this->expenses($firstDay, $today);
    }

    private function pickItems(Collection $equipment): array
    {
        $names = [];

        for ($i = 0, $count = mt_rand(1, 100) <= 65 ? 1 : mt_rand(2, 3); $i < $count; $i++) {
            $pool = mt_rand(1, 100) <= 88 ? self::POPULAR : self::OCCASIONAL;
            $name = array_keys($pool)[mt_rand(0, count($pool) - 1)];
            $names[$name] = mt_rand(...$pool[$name]);
        }

        return collect($names)->map(fn (int $quantity, string $name) => [$equipment[$name], $quantity])->values()->all();
    }

    private function rental(Customer $customer, array $items, CarbonImmutable $start, CarbonImmutable $end, string $status, CarbonImmutable $today): void
    {
        $days = (int) $start->diffInDays($end) + 1;
        $total = collect($items)->sum(fn (array $item) => $item[0]->daily_rate * $item[1] * $days);
        $total = round($total * ($days >= 30 ? 0.70 : ($days >= 7 ? 0.85 : 1)), 2);

        $rental = Rental::create([
            'customer_id' => $customer->id, 'customer_name' => $customer->name,
            'start_date' => $start, 'end_date' => $end, 'status' => $status, 'total' => $total,
        ]);

        foreach ($items as [$equipment, $quantity]) {
            $rental->items()->create(['equipment_id' => $equipment->id, 'quantity' => $quantity, 'daily_rate' => $equipment->daily_rate]);
        }

        $paidAt = $start->addDays(mt_rand(0, 3));
        $stillOwes = $start->gte($today->subDays(12)) && mt_rand(1, 100) <= 20;

        FinancialEntry::create([
            'type' => FinancialEntry::INCOME, 'category' => FinancialEntry::RENTAL_CATEGORY,
            'description' => "Locação nº {$rental->id}, {$customer->name}", 'amount' => $total,
            'due_date' => $start, 'paid_at' => $stillOwes || $paidAt->gt($today) ? null : $paidAt, 'rental_id' => $rental->id,
        ]);
    }

    private function expenses(CarbonImmutable $firstDay, CarbonImmutable $today): void
    {
        $expense = function (string $category, string $description, float $amount, CarbonImmutable $due) use ($today): void {
            FinancialEntry::create([
                'type' => FinancialEntry::EXPENSE, 'category' => $category, 'description' => $description,
                'amount' => $amount, 'due_date' => $due, 'paid_at' => $due->lte($today) ? $due : null,
            ]);
        };

        for ($month = $firstDay->startOfMonth(); $month->lte($today->addMonth()); $month = $month->addMonth()) {
            $label = $month->locale('pt_BR')->isoFormat('MMMM');
            $expense('Folha de pagamento', "Salários de {$label}", 6800, $month->addDays(4));
            $expense('Aluguel e contas', "Aluguel do galpão, luz e internet de {$label}", 3150, $month->addDays(9));
            $expense('Impostos', "Simples Nacional de {$label}", round(mt_rand(1250, 1700), 2), $month->addDays(19));
        }

        for ($day = $firstDay; $day->lte($today); $day = $day->addWeek()) {
            $expense('Combustível e transporte', 'Diesel e entregas da semana', mt_rand(280, 620), $day->addDays(4));

            if (mt_rand(1, 100) <= 60) {
                $what = ['Troca de correia da betoneira', 'Revisão do gerador', 'Discos e brocas', 'Solda em painel de andaime', 'Óleo e filtros', 'Conserto de martelete'][mt_rand(0, 5)];
                $expense('Manutenção', $what, mt_rand(160, 1250), $day->addDays(mt_rand(0, 5)));
            }
        }

        $expense('Compra de equipamento', 'Placa vibratória usada', 6900, $today->subDays(75));
    }

    public static function kindOf(string $name): string
    {
        return preg_match('/^(Construtora|Reformas|Pinturas|Marcenaria|Engenharia|Obras|Telhados|Empreiteira|Pavimentadora|Eventos)\b/', $name) ? Customer::COMPANY : Customer::PERSON;
    }

    private function phone(): string
    {
        return sprintf('(48) 9%04d-%04d', mt_rand(8000, 9999), mt_rand(0, 9999));
    }
}
