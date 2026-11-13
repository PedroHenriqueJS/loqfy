<?php

namespace App\Ai\Tools;

use App\Models\Equipment;
use App\Services\AvailabilityService;
use App\Services\CatalogSearch;
use App\Services\DeliveryZoneResolver;
use Carbon\CarbonImmutable;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Tool as PrismTool;
use Throwable;

class QuoteTools
{
    public function __construct(
        private CatalogSearch $catalog,
        private AvailabilityService $availability,
        private DeliveryZoneResolver $zones,
    ) {}

    public function all(): array
    {
        return [$this->searchEquipment(), $this->findDeliveryZone()];
    }

    private function searchEquipment(): PrismTool
    {
        return Tool::as('search_equipment')
            ->for('Busca equipamentos no catálogo pelo termo usado pelo cliente, o mais parecido primeiro. Com as datas, informa também quantas unidades estão livres no período e, se faltar, alternativas da mesma categoria.')
            ->withStringParameter('term', 'Termo do cliente, por exemplo "betoneira" ou "martelete"')
            ->withNumberParameter('quantity', 'Quantidade pedida. Use 1 se o cliente não disse.')
            ->withStringParameter('start_date', 'Primeiro dia de uso, AAAA-MM-DD. Omita se o período não estiver claro.', required: false)
            ->withStringParameter('end_date', 'Último dia de uso, AAAA-MM-DD. Omita se o período não estiver claro.', required: false)
            ->using(function (string $term, int $quantity = 1, ?string $start_date = null, ?string $end_date = null): string {
                $matches = $this->catalog->search($term, 3);
                [$start, $end] = $this->period($start_date, $end_date);

                $result = ['term' => $term, 'matches' => []];

                if (($start_date || $end_date) && $start === null) {
                    $result['warning'] = 'Período inválido, disponibilidade não conferida. Use datas AAAA-MM-DD com end_date igual ou posterior a start_date.';
                }

                foreach ($matches as $index => $equipment) {
                    $match = $this->describe($equipment);

                    if ($start !== null) {
                        $free = $this->availability->availableQuantity($equipment, $start, $end);
                        $match['available_quantity'] = $free;
                        $match['available'] = $free >= $quantity;

                        if ($index === 0 && $free < $quantity) {
                            $match['alternatives'] = $this->availability->alternatives($equipment, $start, $end)
                                ->map(fn (array $row) => $this->describe($row['equipment']) + ['available_quantity' => $row['available_quantity'], 'available' => $row['available_quantity'] >= $quantity])
                                ->all();
                        }
                    }

                    $result['matches'][] = $match;
                }

                return $this->json($result);
            });
    }

    private function findDeliveryZone(): PrismTool
    {
        return Tool::as('find_delivery_zone')
            ->for('Confere se a locadora entrega em um bairro ou cidade.')
            ->withStringParameter('neighborhood', 'Bairro ou cidade citado pelo cliente')
            ->using(function (string $neighborhood): string {
                $zone = $this->zones->resolve($neighborhood);

                return $this->json([
                    'neighborhood' => $neighborhood,
                    'delivers' => $zone !== null,
                    'zone' => $zone?->name,
                ]);
            });
    }

    private function describe(Equipment $equipment): array
    {
        return [
            'equipment_id' => $equipment->id,
            'name' => $equipment->name,
            'description' => $equipment->description,
        ];
    }

    private function period(?string $startDate, ?string $endDate): array
    {
        if (! $startDate || ! $endDate) {
            return [null, null];
        }

        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $startDate);
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $endDate);
        } catch (Throwable) {
            return [null, null];
        }

        return $end->lt($start) ? [null, null] : [$start, $end];
    }

    private function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
