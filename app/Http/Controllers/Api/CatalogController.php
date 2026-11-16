<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryZoneResource;
use App\Http\Resources\EquipmentResource;
use App\Models\DeliveryZone;
use App\Models\Equipment;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(): array
    {
        return [
            'equipment' => EquipmentResource::collection(Equipment::orderBy('category')->orderBy('name')->get()),
            'delivery_zones' => DeliveryZoneResource::collection(DeliveryZone::all()),
        ];
    }

    public function storeEquipment(Request $request): EquipmentResource
    {
        return new EquipmentResource(Equipment::create($this->equipment($request)));
    }

    public function updateEquipment(Request $request, Equipment $equipment): EquipmentResource
    {
        $equipment->update($this->equipment($request));

        return new EquipmentResource($equipment);
    }

    public function destroyEquipment(Equipment $equipment): array
    {
        abort_if($equipment->isInUse(), 422, 'Este equipamento aparece em orçamentos ou locações e não pode ser excluído.');

        $equipment->delete();

        return ['deleted' => true];
    }

    public function storeZone(Request $request): DeliveryZoneResource
    {
        return new DeliveryZoneResource(DeliveryZone::create($this->zone($request)));
    }

    public function updateZone(Request $request, DeliveryZone $deliveryZone): DeliveryZoneResource
    {
        $deliveryZone->update($this->zone($request));

        return new DeliveryZoneResource($deliveryZone);
    }

    public function destroyZone(DeliveryZone $deliveryZone): array
    {
        $deliveryZone->delete();

        return ['deleted' => true];
    }

    private function equipment(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'aliases' => ['present', 'array'],
            'aliases.*' => ['string', 'max:60'],
            'daily_rate' => ['required', 'numeric', 'min:0', 'max:99999'],
            'quantity_total' => ['required', 'integer', 'min:0', 'max:99999'],
        ]);
    }

    private function zone(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'neighborhoods' => ['required', 'array', 'min:1'],
            'neighborhoods.*' => ['string', 'max:60'],
            'fee' => ['required', 'numeric', 'min:0', 'max:9999'],
        ]);
    }
}
