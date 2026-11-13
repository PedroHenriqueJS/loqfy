<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Models\Equipment;
use App\Models\QuoteItem;
use App\Models\RentalItem;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(): array
    {
        return [
            'equipment' => Equipment::orderBy('category')->orderBy('name')->get(),
            'delivery_zones' => DeliveryZone::all(),
        ];
    }

    public function storeEquipment(Request $request): Equipment
    {
        return Equipment::create($this->equipment($request));
    }

    public function updateEquipment(Request $request, Equipment $equipment): Equipment
    {
        $equipment->update($this->equipment($request));

        return $equipment;
    }

    public function destroyEquipment(Equipment $equipment): array
    {
        $inUse = QuoteItem::where('equipment_id', $equipment->id)->orWhere('substitutes_equipment_id', $equipment->id)->exists()
            || RentalItem::where('equipment_id', $equipment->id)->exists();

        abort_if($inUse, 422, 'Este equipamento aparece em orçamentos ou locações e não pode ser excluído.');

        $equipment->delete();

        return ['deleted' => true];
    }

    public function storeZone(Request $request): DeliveryZone
    {
        return DeliveryZone::create($this->zone($request));
    }

    public function updateZone(Request $request, DeliveryZone $deliveryZone): DeliveryZone
    {
        $deliveryZone->update($this->zone($request));

        return $deliveryZone;
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
