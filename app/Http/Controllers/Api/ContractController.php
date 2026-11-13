<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Services\PricingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ContractController extends Controller
{
    public function show(Rental $rental, PricingService $pricing): Response
    {
        $rental->load('items.equipment', 'quote.deliveryZone', 'customer');

        $days = $pricing->days($rental->start_date, $rental->end_date);
        $lines = $rental->items->map(fn (RentalItem $item) => [
            'name' => $item->equipment->name,
            'description' => $item->equipment->description,
            'quantity' => $item->quantity,
            'daily_rate' => $item->daily_rate,
            'total' => $pricing->lineTotal($item->daily_rate, $item->quantity, $days),
        ]);

        $subtotal = round($lines->sum('total'), 2);
        $deliveryFee = $rental->quote?->delivery_fee ?? 0.0;

        $pdf = Pdf::loadView('contracts.rental', [
            'rental' => $rental,
            'customer' => $rental->customer,
            'company' => config('loqfy.company'),
            'lines' => $lines,
            'days' => $days,
            'subtotal' => $subtotal,
            'discount' => $rental->quote?->discount ?? $pricing->discount($subtotal, $days),
            'deliveryFee' => $deliveryFee,
            'deliveryPlace' => $rental->quote?->neighborhood,
            'total' => $rental->total,
            'issuedAt' => now(),
        ])->setPaper('a4');

        return $pdf->stream("contrato-locacao-{$rental->id}.pdf");
    }
}
