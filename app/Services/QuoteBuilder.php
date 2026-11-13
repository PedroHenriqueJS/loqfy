<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Quote;
use App\Models\RentalRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class QuoteBuilder
{
    public function __construct(
        private AvailabilityService $availability,
        private PricingService $pricing,
        private DeliveryZoneResolver $zones,
    ) {}

    public function build(Quote $quote, array $data): Quote
    {
        $issues = $this->reportedIssues($data['issues'] ?? []);

        $start = $this->date($data['start_date'] ?? null);
        $end = $this->date($data['end_date'] ?? null);

        if ($start && $end && $end->lt($start)) {
            $issues[] = $this->issue('missing_info', 'A data final é anterior à data inicial.');
            $start = $end = null;
        }

        if (! $start || ! $end) {
            $start = $end = null;
            $issues[] = $this->issue('missing_info', 'Período da locação não definido.');
        }

        $days = $start ? $this->pricing->days($start, $end) : 0;

        $lines = [];
        foreach ($data['items'] ?? [] as $item) {
            $equipment = Equipment::find((int) ($item['equipment_id'] ?? 0));
            $term = $item['requested_term'] ?? null;

            if ($equipment === null) {
                $issues[] = $this->issue('not_in_catalog', 'Item fora do catálogo'.($term ? ": {$term}." : '.'));

                continue;
            }

            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $available = true;

            if ($start) {
                $free = $this->availability->availableQuantity($equipment, $start, $end);
                $available = $free >= $quantity;

                if (! $available) {
                    $issues[] = $this->issue('unavailable', "{$equipment->name}: {$free} disponível(is) no período, pedido de {$quantity}.");
                }
            }

            $substitutes = Equipment::find((int) ($item['substitutes_equipment_id'] ?? 0));
            if ($substitutes && $substitutes->id !== $equipment->id) {
                $issues[] = $this->issue('substitution', "{$substitutes->name} indisponível, substituído por {$equipment->name}.");
            } else {
                $substitutes = null;
            }

            $lines[] = [
                'equipment_id' => $equipment->id,
                'substitutes_equipment_id' => $substitutes?->id,
                'requested_term' => $term,
                'quantity' => $quantity,
                'daily_rate' => $equipment->daily_rate,
                'line_total' => $this->pricing->lineTotal($equipment->daily_rate, $quantity, $days),
                'available' => $available,
            ];
        }

        if ($lines === []) {
            $issues[] = $this->issue('missing_info', 'Nenhum equipamento do catálogo no orçamento.');
        }

        $neighborhood = $data['neighborhood'] ?? null;
        $wantsDelivery = (bool) ($data['delivery'] ?? ($neighborhood !== null));
        $zone = $wantsDelivery ? $this->zones->resolve($neighborhood) : null;

        if ($wantsDelivery && $zone === null) {
            $issues[] = $this->issue('delivery_zone_unknown', $neighborhood
                ? "Não há zona de entrega cadastrada para \"{$neighborhood}\"."
                : 'Entrega pedida sem bairro informado.');
        }

        $subtotal = round(array_sum(array_column($lines, 'line_total')), 2);
        $discount = $this->pricing->discount($subtotal, $days);
        $deliveryFee = $zone?->fee ?? 0.0;
        $issues = $this->unique($issues);

        $customer = Customer::find((int) ($data['customer_id'] ?? 0)) ?? $this->customerNamed($data['customer_name'] ?? null);
        $needsReview = $issues !== [] || ($data['confidence'] ?? null) === 'low';

        return DB::transaction(function () use ($quote, $data, $customer, $needsReview, $start, $end, $days, $zone, $neighborhood, $deliveryFee, $subtotal, $discount, $issues, $lines) {
            $quote->fill([
                'customer_id' => $customer?->id,
                'customer_name' => $customer?->name ?? ($data['customer_name'] ?? null),
                'needs_review' => $needsReview,
                'start_date' => $start,
                'end_date' => $end,
                'days' => $days,
                'delivery_zone_id' => $zone?->id,
                'neighborhood' => $neighborhood,
                'delivery_fee' => $deliveryFee,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => round($subtotal - $discount + $deliveryFee, 2),
                'issues' => $issues,
                'confidence' => $data['confidence'] ?? null,
            ])->save();

            $quote->items()->delete();
            $quote->items()->createMany($lines);

            $quote->rentalRequest?->update([
                'status' => $needsReview ? RentalRequest::NEEDS_REVIEW : RentalRequest::READY,
                'customer_name' => $quote->customer_name,
            ]);

            return $quote->load('items.equipment', 'items.substitutes', 'deliveryZone', 'customer');
        });
    }

    private function customerNamed(?string $name): ?Customer
    {
        $name = trim($name ?? '');
        if ($name === '') {
            return null;
        }

        $matches = Customer::whereRaw('lower(name) = ?', [mb_strtolower($name)])->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }
    }

    private function reportedIssues(mixed $reported): array
    {
        $issues = [];

        foreach (is_array($reported) ? $reported : [] as $issue) {
            if (! is_array($issue) || ! is_string($issue['message'] ?? null)) {
                continue;
            }

            $type = in_array($issue['type'] ?? null, ['unavailable', 'not_in_catalog', 'ambiguous', 'missing_info'], true)
                ? $issue['type']
                : 'ambiguous';

            $issues[] = ['type' => $type, 'message' => $issue['message'], 'source' => 'ai'];
        }

        return $issues;
    }

    private function issue(string $type, string $message): array
    {
        return ['type' => $type, 'message' => $message, 'source' => 'system'];
    }

    private function unique(array $issues): array
    {
        $systemReportsPeriod = collect($issues)->contains(fn (array $issue) => $issue['source'] === 'system' && $issue['type'] === 'missing_info');

        return array_values(array_filter(
            $issues,
            fn (array $issue) => $issue['source'] === 'system' || ! ($systemReportsPeriod && $issue['type'] === 'missing_info'),
        ));
    }
}
