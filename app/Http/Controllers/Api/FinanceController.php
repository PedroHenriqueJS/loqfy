<?php

namespace App\Http\Controllers\Api;

use App\Ai\ReceiptReader;
use App\Http\Controllers\Controller;
use App\Models\AiRun;
use App\Models\FinancialEntry;
use App\Services\DuplicatePhotos;
use App\Services\FinanceReport;
use App\Services\ReceiptChecks;
use App\Support\PhotoFingerprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Prism\Prism\Exceptions\PrismException;

class FinanceController extends Controller
{
    private const PHOTO_MESSAGES = [
        'photo.mimes' => 'Envie a foto em JPG, PNG ou WebP.',
        'photo.max' => 'A foto passa de 8 MB.',
        'photo.uploaded' => 'A foto não chegou ao servidor. Tente de novo.',
    ];

    public function summary(FinanceReport $report): array
    {
        return $report->summary() + ['categories' => FinancialEntry::CATEGORIES];
    }

    public function index(): array
    {
        return FinancialEntry::orderByDesc('due_date')->orderByDesc('id')->limit(400)->get()
            ->map(fn (FinancialEntry $entry) => $this->present($entry))
            ->all();
    }

    public function store(Request $request): array
    {
        return $this->present(FinancialEntry::create($this->validated($request)));
    }

    public function update(Request $request, FinancialEntry $entry): array
    {
        $entry->update($this->validated($request));

        return $this->present($entry);
    }

    public function destroy(FinancialEntry $entry): array
    {
        $entry->delete();

        return ['deleted' => true];
    }

    public function readReceipt(Request $request, ReceiptReader $reader, DuplicatePhotos $duplicates, ReceiptChecks $checks): array
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192']], self::PHOTO_MESSAGES);
        $path = $request->file('photo')->store('receipts', 'public');
        $earlierUses = $duplicates->find(PhotoFingerprint::of(Storage::disk('public')->path($path)));

        try {
            $result = $reader->read(Storage::disk('public')->path($path), today());
        } catch (PrismException $e) {
            abort(503, 'O comprovante não pôde ser lido agora. Tente de novo em um minuto ou preencha à mão.');
        }

        AiRun::create([
            'feature' => 'receipt',
            'model' => config('loqfy.ai.model'),
            'output' => $result->extraction ?? ['raw' => $result->rawText],
            'steps' => $result->steps,
            'prompt_tokens' => $result->promptTokens,
            'completion_tokens' => $result->completionTokens,
            'latency_ms' => $result->latencyMs,
            'success' => $result->error === null,
            'error' => $result->error,
        ]);

        return [
            'receipt_photo' => $path,
            'receipt_photo_url' => '/storage/'.$path,
            'suggestion' => $result->extraction,
            'checks' => $checks->check($result->extraction, $earlierUses, today()),
        ];
    }

    public function togglePaid(FinancialEntry $entry): array
    {
        $entry->update(['paid_at' => $entry->paid_at === null ? today() : null]);

        return $this->present($entry);
    }

    private function present(FinancialEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'type' => $entry->type,
            'category' => $entry->category,
            'description' => $entry->description,
            'amount' => $entry->amount,
            'due_date' => $entry->due_date->toDateString(),
            'paid_at' => $entry->paid_at?->toDateString(),
            'status' => $entry->status(),
            'rental_id' => $entry->rental_id,
            'origin' => $entry->origin ?? 'manual',
            'receipt_photo' => $entry->receipt_photo,
            'receipt_photo_url' => $entry->receipt_photo ? '/storage/'.$entry->receipt_photo : null,
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in([FinancialEntry::INCOME, FinancialEntry::EXPENSE])],
            'category' => ['required', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'paid_at' => ['nullable', 'date_format:Y-m-d'],
            'origin' => ['nullable', Rule::in(['manual', 'ai'])],
            'receipt_photo' => ['nullable', 'string', 'regex:/^receipts\/[A-Za-z0-9]+\.[a-z]+$/'],
        ], ['receipt_photo.regex' => 'Foto de comprovante inválida. Leia o comprovante de novo.']);

        $data['origin'] ??= 'manual';

        $data['receipt_photo_hash'] = isset($data['receipt_photo']) && Storage::disk('public')->exists($data['receipt_photo'])
            ? PhotoFingerprint::of(Storage::disk('public')->path($data['receipt_photo']))
            : null;

        return $data;
    }
}
