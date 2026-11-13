<?php

namespace App\Services;

use App\Models\FinancialEntry;
use App\Models\Quote;
use App\Models\Rental;
use App\Models\RentalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RentalService
{
    public function __construct(private AvailabilityService $availability) {}

    public function approve(Quote $quote): Rental
    {
        return DB::transaction(function () use ($quote) {
            $quote->load('items.equipment', 'rentalRequest', 'customer');

            if ($quote->status !== 'draft') {
                throw ValidationException::withMessages(['quote' => 'Este orçamento já foi aprovado ou descartado.']);
            }

            if (! $quote->start_date || ! $quote->end_date) {
                throw ValidationException::withMessages(['quote' => 'Defina o período antes de aprovar.']);
            }

            if ($quote->items->isEmpty()) {
                throw ValidationException::withMessages(['quote' => 'Inclua ao menos um equipamento antes de aprovar.']);
            }

            $customer = $quote->customer;
            if ($customer === null) {
                throw ValidationException::withMessages(['quote' => 'Vincule o orçamento a um cliente cadastrado antes de aprovar.']);
            }
            if (! $customer->isVerified()) {
                throw ValidationException::withMessages(['quote' => "Confira o documento de {$customer->name} no cadastro antes de aprovar."]);
            }

            foreach ($quote->items as $item) {
                $free = $this->availability->availableQuantity($item->equipment, $quote->start_date, $quote->end_date);

                if ($free < $item->quantity) {
                    throw ValidationException::withMessages([
                        'quote' => "{$item->equipment->name}: {$free} disponível(is) no período, orçamento pede {$item->quantity}.",
                    ]);
                }
            }

            $rental = Rental::create([
                'quote_id' => $quote->id,
                'customer_id' => $quote->customer_id,
                'customer_name' => $quote->customer_name,
                'start_date' => $quote->start_date,
                'end_date' => $quote->end_date,
                'status' => Rental::RESERVED,
                'total' => $quote->total,
            ]);

            $rental->items()->createMany($quote->items->map(fn ($item) => [
                'equipment_id' => $item->equipment_id,
                'quantity' => $item->quantity,
                'daily_rate' => $item->daily_rate,
            ])->all());

            FinancialEntry::create([
                'type' => FinancialEntry::INCOME,
                'category' => FinancialEntry::RENTAL_CATEGORY,
                'description' => "Locação nº {$rental->id}".($rental->customer_name ? ", {$rental->customer_name}" : ''),
                'amount' => $rental->total,
                'due_date' => $rental->start_date,
                'rental_id' => $rental->id,
            ]);

            $quote->update(['status' => 'approved']);
            $quote->rentalRequest?->update(['status' => RentalRequest::APPROVED]);

            return $rental;
        });
    }
}
