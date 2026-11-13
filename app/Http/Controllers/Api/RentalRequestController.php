<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RentalRequestResource;
use App\Jobs\ProcessRentalRequest;
use App\Models\Quote;
use App\Models\RentalRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RentalRequestController extends Controller
{
    private const DETAIL = ['quote.items.equipment', 'quote.items.substitutes', 'quote.deliveryZone', 'quote.customer', 'latestAiRun'];

    public function index(): AnonymousResourceCollection
    {
        return RentalRequestResource::collection(
            RentalRequest::with('quote.items.equipment', 'quote.items.substitutes', 'quote.deliveryZone', 'quote.customer')->latest('id')->limit(100)->get()
        );
    }

    public function store(Request $request): RentalRequestResource
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'min:3', 'max:2000'],
            'channel' => ['nullable', 'in:whatsapp,phone,counter,email'],
        ]);

        $rentalRequest = RentalRequest::create([
            'raw_text' => $data['text'],
            'channel' => $data['channel'] ?? 'whatsapp',
        ]);

        ProcessRentalRequest::dispatch($rentalRequest);

        return new RentalRequestResource($rentalRequest);
    }

    public function show(RentalRequest $rentalRequest): RentalRequestResource
    {
        return new RentalRequestResource($rentalRequest->load(self::DETAIL));
    }

    public function reprocess(RentalRequest $rentalRequest): RentalRequestResource
    {
        abort_if($rentalRequest->status === RentalRequest::APPROVED, 422, 'Pedido já aprovado.');

        $rentalRequest->update(['status' => RentalRequest::PENDING, 'error' => null]);
        ProcessRentalRequest::dispatch($rentalRequest);

        return new RentalRequestResource($rentalRequest->load(self::DETAIL));
    }

    public function discard(RentalRequest $rentalRequest): RentalRequestResource
    {
        abort_if($rentalRequest->status === RentalRequest::APPROVED, 422, 'Pedido já aprovado.');

        $rentalRequest->update(['status' => RentalRequest::DISCARDED]);
        Quote::where('rental_request_id', $rentalRequest->id)->where('status', 'draft')->update(['status' => 'discarded']);

        return new RentalRequestResource($rentalRequest->load(self::DETAIL));
    }
}
