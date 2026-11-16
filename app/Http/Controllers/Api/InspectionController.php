<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InspectionDecisionRequest;
use App\Http\Requests\PhotoUploadRequest;
use App\Http\Resources\InspectionResource;
use App\Models\Inspection;
use App\Models\RentalItem;
use App\Services\InspectionService;

class InspectionController extends Controller
{
    public function __construct(private InspectionService $inspections) {}

    public function checkout(PhotoUploadRequest $request, RentalItem $rentalItem): InspectionResource
    {
        return new InspectionResource($this->inspections->recordCheckout($rentalItem, $request->file('photo')));
    }

    public function return(PhotoUploadRequest $request, RentalItem $rentalItem): InspectionResource
    {
        return new InspectionResource($this->inspections->recordReturn($rentalItem, $request->file('photo')));
    }

    public function manualReturn(InspectionDecisionRequest $request, RentalItem $rentalItem): InspectionResource
    {
        return new InspectionResource($this->inspections->recordManualReturn($rentalItem, $request->validated('decision'), $request->validated('note')));
    }

    public function show(Inspection $inspection): InspectionResource
    {
        return new InspectionResource($inspection);
    }

    public function decide(InspectionDecisionRequest $request, Inspection $inspection): InspectionResource
    {
        return new InspectionResource($this->inspections->decide($inspection, $request->validated('decision'), $request->validated('note')));
    }
}
