<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RentalResource;
use App\Models\Rental;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RentalController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return RentalResource::collection(
            Rental::with('items.equipment', 'items.inspection', 'quote')->latest('id')->limit(200)->get()
        );
    }

    public function checkout(Rental $rental): RentalResource
    {
        abort_unless($rental->status === Rental::RESERVED, 422, 'Esta locação já saiu.');

        $rental->update(['status' => Rental::OUT]);

        return new RentalResource($rental->load('items.equipment', 'items.inspection', 'quote'));
    }
}
