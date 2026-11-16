<?php

namespace App\Services;

use App\Ai\ReceiptReader;
use App\Support\PhotoFingerprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ReceiptIntake
{
    public function __construct(
        private ReceiptReader $reader,
        private DuplicatePhotos $duplicates,
        private ReceiptChecks $checks,
        private AiRunLog $log,
    ) {}

    public function read(UploadedFile $photo): array
    {
        $path = $photo->store('receipts', 'public');
        $fullPath = Storage::disk('public')->path($path);
        $earlierUses = $this->duplicates->find(PhotoFingerprint::of($fullPath));

        $result = $this->reader->read($fullPath, today());
        $this->log->record('receipt', $result);

        return [
            'receipt_photo' => $path,
            'receipt_photo_url' => '/storage/'.$path,
            'suggestion' => $result->extraction,
            'checks' => $this->checks->check($result->extraction, $earlierUses, today()),
        ];
    }

    public function fingerprint(?string $path): ?string
    {
        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return PhotoFingerprint::of(Storage::disk('public')->path($path));
    }
}
