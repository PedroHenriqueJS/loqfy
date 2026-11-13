<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InspectionResource;
use App\Jobs\AnalyzeInspection;
use App\Models\Inspection;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Services\DuplicatePhotos;
use App\Support\PhotoFingerprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InspectionController extends Controller
{
    private const PHOTO_MESSAGES = [
        'photo.mimes' => 'Envie a foto em JPG, PNG ou WebP.',
        'photo.max' => 'A foto passa de 8 MB.',
        'photo.uploaded' => 'A foto não chegou ao servidor. Tente de novo.',
    ];

    public function checkout(Request $request, RentalItem $rentalItem, DuplicatePhotos $duplicates): InspectionResource
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192']], self::PHOTO_MESSAGES);

        $path = $request->file('photo')->store('inspections', 'public');
        $hash = PhotoFingerprint::of(Storage::disk('public')->path($path));
        $existing = $rentalItem->inspection;

        $inspection = Inspection::updateOrCreate(
            ['rental_item_id' => $rentalItem->id],
            [
                'checkout_photo' => $path,
                'checkout_photo_hash' => $hash,
                'return_photo_hash' => null,
                'checks' => $this->duplicateChecks($duplicates->find($hash, ['inspection' => $existing?->id]), 'saída'),
                'return_photo' => null,
                'status' => Inspection::AWAITING_RETURN,
                'ai_result' => null,
                'decision' => null,
                'decision_note' => null,
                'decided_at' => null,
                'error' => null,
            ],
        );

        $rentalItem->rental->update(['status' => Rental::OUT]);

        return new InspectionResource($inspection);
    }

    public function return(Request $request, RentalItem $rentalItem, DuplicatePhotos $duplicates): InspectionResource
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192']], self::PHOTO_MESSAGES);

        $inspection = $rentalItem->inspection;
        abort_if($inspection === null || $inspection->checkout_photo === null, 422, 'Registre a foto de saída antes da devolução.');

        $path = $request->file('photo')->store('inspections', 'public');
        $hash = PhotoFingerprint::of(Storage::disk('public')->path($path));

        $checks = collect($inspection->checks ?? [])->where('moment', 'saída')->values()->all();
        if ($inspection->checkout_photo_hash && PhotoFingerprint::distance($hash, $inspection->checkout_photo_hash) <= PhotoFingerprint::SAME_PHOTO) {
            $checks[] = ['moment' => 'devolução', 'code' => 'same_as_checkout', 'message' => 'A foto de devolução é a mesma foto da saída. Assim não há o que comparar: fotografe o equipamento de novo.'];
        }
        $checks = [...$checks, ...$this->duplicateChecks($duplicates->find($hash, ['inspection' => $inspection->id]), 'devolução')];

        $inspection->update([
            'return_photo' => $path,
            'return_photo_hash' => $hash,
            'checks' => $checks,
            'status' => Inspection::PENDING,
            'ai_result' => null,
            'decision' => null,
            'decision_note' => null,
            'decided_at' => null,
            'error' => null,
        ]);

        AnalyzeInspection::dispatch($inspection);

        return new InspectionResource($inspection);
    }

    public function manualReturn(Request $request, RentalItem $rentalItem): InspectionResource
    {
        $data = $request->validate([
            'decision' => ['required', 'in:'.Inspection::NO_DAMAGE.','.Inspection::DAMAGE_CONFIRMED],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $inspection = Inspection::updateOrCreate(
            ['rental_item_id' => $rentalItem->id],
            [
                'status' => Inspection::ANALYZED,
                'decision' => $data['decision'],
                'decision_note' => $data['note'] ?? null,
                'decided_at' => now(),
                'error' => null,
            ],
        );

        $this->closeRentalWhenAllReturned($rentalItem->rental);

        return new InspectionResource($inspection);
    }

    public function show(Inspection $inspection): InspectionResource
    {
        return new InspectionResource($inspection);
    }

    public function decide(Request $request, Inspection $inspection): InspectionResource
    {
        $data = $request->validate([
            'decision' => ['required', 'in:'.Inspection::NO_DAMAGE.','.Inspection::DAMAGE_CONFIRMED],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        abort_if($inspection->return_photo === null, 422, 'Ainda não há foto de devolução.');

        $inspection->update([
            'decision' => $data['decision'],
            'decision_note' => $data['note'] ?? null,
            'decided_at' => now(),
        ]);

        $this->closeRentalWhenAllReturned($inspection->rentalItem->rental);

        return new InspectionResource($inspection);
    }

    private function duplicateChecks(array $uses, string $moment): array
    {
        return array_map(fn (string $use) => [
            'moment' => $moment,
            'code' => 'duplicate_photo',
            'message' => "A foto de {$moment} já foi usada {$use}.",
        ], $uses);
    }

    private function closeRentalWhenAllReturned(Rental $rental): void
    {
        $allDecided = $rental->items()->with('inspection')->get()->every(fn (RentalItem $item) => $item->inspection?->decision !== null);

        if ($allDecided) {
            $rental->update(['status' => Rental::RETURNED]);
        }
    }
}
