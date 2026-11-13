<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\Services\QuoteBuilder;
use App\Services\RentalService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuoteController extends Controller
{
    private const DETAIL = ['items.equipment', 'items.substitutes', 'deliveryZone', 'customer'];

    public function index(): AnonymousResourceCollection
    {
        return QuoteResource::collection(Quote::with(self::DETAIL)->latest('id')->limit(200)->get());
    }

    public function store(Request $request, QuoteBuilder $builder): QuoteResource
    {
        return new QuoteResource($builder->build(new Quote(['origin' => Quote::MANUAL]), $this->validated($request)));
    }

    public function show(Quote $quote): QuoteResource
    {
        return new QuoteResource($quote->load(self::DETAIL));
    }

    public function update(Request $request, Quote $quote, QuoteBuilder $builder): QuoteResource
    {
        abort_unless($quote->status === 'draft', 422, 'Este orçamento não pode mais ser editado.');

        return new QuoteResource($builder->build($quote, $this->validated($request)));
    }

    public function approve(Quote $quote, RentalService $rentals): QuoteResource
    {
        $rentals->approve($quote);

        return new QuoteResource($quote->fresh()->load(self::DETAIL));
    }

    public function discard(Quote $quote): QuoteResource
    {
        abort_unless($quote->status === 'draft', 422, 'Este orçamento não pode mais ser descartado.');

        $quote->update(['status' => 'discarded']);
        $quote->rentalRequest?->update(['status' => 'discarded']);

        return new QuoteResource($quote->load(self::DETAIL));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'items' => ['present', 'array'],
            'items.*.equipment_id' => ['required', 'integer', 'exists:equipment,id'],
            'items.*.requested_term' => ['nullable', 'string', 'max:120'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'delivery' => ['required', 'boolean'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
        ]);
    }
}
