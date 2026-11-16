<?php

namespace App\Services;

use App\Jobs\AnalyzeInspection;
use App\Models\Inspection;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Support\PhotoFingerprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class InspectionService
{
    private const RESET = [
        'ai_result' => null,
        'decision' => null,
        'decision_note' => null,
        'decided_at' => null,
        'error' => null,
    ];

    public function __construct(private DuplicatePhotos $duplicates) {}

    public function recordCheckout(RentalItem $item, UploadedFile $photo): Inspection
    {
        [$path, $hash] = $this->store($photo);
        $existing = $item->inspection;

        $inspection = Inspection::updateOrCreate(['rental_item_id' => $item->id], [
            'checkout_photo' => $path,
            'checkout_photo_hash' => $hash,
            'return_photo' => null,
            'return_photo_hash' => null,
            'checks' => $this->duplicateChecks($hash, 'saída', $existing?->id),
            'status' => Inspection::AWAITING_RETURN,
        ] + self::RESET);

        $item->rental->update(['status' => Rental::OUT]);

        return $inspection;
    }

    public function recordReturn(RentalItem $item, UploadedFile $photo): Inspection
    {
        $inspection = $item->inspection;

        if ($inspection === null || $inspection->checkout_photo === null) {
            throw ValidationException::withMessages(['photo' => 'Registre a foto de saída antes da devolução.']);
        }

        [$path, $hash] = $this->store($photo);

        $checks = collect($inspection->checks ?? [])->where('moment', 'saída')->values()->all();

        if ($inspection->checkout_photo_hash && PhotoFingerprint::distance($hash, $inspection->checkout_photo_hash) <= PhotoFingerprint::SAME_PHOTO) {
            $checks[] = [
                'moment' => 'devolução',
                'code' => 'same_as_checkout',
                'message' => 'A foto de devolução é a mesma foto da saída. Assim não há o que comparar: fotografe o equipamento de novo.',
            ];
        }

        $inspection->update([
            'return_photo' => $path,
            'return_photo_hash' => $hash,
            'checks' => [...$checks, ...$this->duplicateChecks($hash, 'devolução', $inspection->id)],
            'status' => Inspection::PENDING,
        ] + self::RESET);

        AnalyzeInspection::dispatch($inspection);

        return $inspection;
    }

    public function recordManualReturn(RentalItem $item, string $decision, ?string $note): Inspection
    {
        $inspection = Inspection::updateOrCreate(['rental_item_id' => $item->id], [
            'status' => Inspection::ANALYZED,
            'decision' => $decision,
            'decision_note' => $note,
            'decided_at' => now(),
            'error' => null,
        ]);

        $this->closeRentalWhenAllReturned($item->rental);

        return $inspection;
    }

    public function decide(Inspection $inspection, string $decision, ?string $note): Inspection
    {
        if ($inspection->return_photo === null) {
            throw ValidationException::withMessages(['decision' => 'Ainda não há foto de devolução.']);
        }

        $inspection->update([
            'decision' => $decision,
            'decision_note' => $note,
            'decided_at' => now(),
        ]);

        $this->closeRentalWhenAllReturned($inspection->rentalItem->rental);

        return $inspection;
    }

    private function store(UploadedFile $photo): array
    {
        $path = $photo->store('inspections', 'public');

        return [$path, PhotoFingerprint::of(Storage::disk('public')->path($path))];
    }

    private function duplicateChecks(string $hash, string $moment, ?int $exceptInspection): array
    {
        return array_map(fn (string $use) => [
            'moment' => $moment,
            'code' => 'duplicate_photo',
            'message' => "A foto de {$moment} já foi usada {$use}.",
        ], $this->duplicates->find($hash, ['inspection' => $exceptInspection]));
    }

    private function closeRentalWhenAllReturned(Rental $rental): void
    {
        $allDecided = $rental->items()->with('inspection')->get()->every(fn (RentalItem $item) => $item->inspection?->decision !== null);

        if ($allDecided) {
            $rental->update(['status' => Rental::RETURNED]);
        }
    }
}
