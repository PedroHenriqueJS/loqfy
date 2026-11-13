<?php

namespace App\Services;

use App\Models\FinancialEntry;
use App\Models\Inspection;
use App\Support\PhotoFingerprint;
use Illuminate\Support\Collection;

class DuplicatePhotos
{
    public function find(string $hash, array $except = []): array
    {
        $uses = [];

        FinancialEntry::whereNotNull('receipt_photo_hash')
            ->when($except['entry'] ?? null, fn ($query, $id) => $query->whereKeyNot($id))
            ->get(['id', 'description', 'due_date', 'receipt_photo_hash'])
            ->filter(fn (FinancialEntry $entry) => PhotoFingerprint::distance($hash, $entry->receipt_photo_hash) <= PhotoFingerprint::SAME_PHOTO)
            ->each(function (FinancialEntry $entry) use (&$uses) {
                $uses[] = "no lançamento \"{$entry->description}\", de {$entry->due_date->format('d/m/Y')}";
            });

        $this->inspections($except['inspection'] ?? null)->each(function (Inspection $inspection) use ($hash, &$uses) {
            foreach (['checkout_photo_hash' => 'saída', 'return_photo_hash' => 'devolução'] as $field => $moment) {
                if ($inspection->{$field} && PhotoFingerprint::distance($hash, $inspection->{$field}) <= PhotoFingerprint::SAME_PHOTO) {
                    $item = $inspection->rentalItem;
                    $uses[] = "na foto de {$moment} de {$item->equipment->name}, locação nº {$item->rental_id}";
                }
            }
        });

        return $uses;
    }

    private function inspections(?int $except): Collection
    {
        return Inspection::with('rentalItem.equipment')
            ->where(fn ($query) => $query->whereNotNull('checkout_photo_hash')->orWhereNotNull('return_photo_hash'))
            ->when($except, fn ($query, $id) => $query->whereKeyNot($id))
            ->get();
    }
}
