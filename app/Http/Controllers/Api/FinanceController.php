<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FinancialEntryRequest;
use App\Http\Requests\PhotoUploadRequest;
use App\Http\Resources\FinancialEntryResource;
use App\Models\FinancialEntry;
use App\Services\FinanceReport;
use App\Services\ReceiptIntake;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Prism\Prism\Exceptions\PrismException;

class FinanceController extends Controller
{
    public function summary(FinanceReport $report): array
    {
        return $report->summary() + ['categories' => FinancialEntry::CATEGORIES];
    }

    public function index(): AnonymousResourceCollection
    {
        return FinancialEntryResource::collection(FinancialEntry::orderByDesc('due_date')->orderByDesc('id')->limit(400)->get());
    }

    public function store(FinancialEntryRequest $request, ReceiptIntake $receipts): FinancialEntryResource
    {
        return new FinancialEntryResource(FinancialEntry::create($this->attributes($request, $receipts)));
    }

    public function update(FinancialEntryRequest $request, FinancialEntry $entry, ReceiptIntake $receipts): FinancialEntryResource
    {
        $entry->update($this->attributes($request, $receipts));

        return new FinancialEntryResource($entry);
    }

    public function destroy(FinancialEntry $entry): array
    {
        $entry->delete();

        return ['deleted' => true];
    }

    public function readReceipt(PhotoUploadRequest $request, ReceiptIntake $receipts): array
    {
        try {
            return $receipts->read($request->file('photo'));
        } catch (PrismException) {
            abort(503, 'O comprovante não pôde ser lido agora. Tente de novo em um minuto ou preencha à mão.');
        }
    }

    public function togglePaid(FinancialEntry $entry): FinancialEntryResource
    {
        $entry->update(['paid_at' => $entry->paid_at === null ? today() : null]);

        return new FinancialEntryResource($entry);
    }

    private function attributes(FinancialEntryRequest $request, ReceiptIntake $receipts): array
    {
        $data = $request->validated();
        $data['origin'] ??= 'manual';
        $data['receipt_photo_hash'] = $receipts->fingerprint($data['receipt_photo'] ?? null);

        return $data;
    }
}
