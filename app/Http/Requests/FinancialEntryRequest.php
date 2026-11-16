<?php

namespace App\Http\Requests;

use App\Models\FinancialEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinancialEntryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([FinancialEntry::INCOME, FinancialEntry::EXPENSE])],
            'category' => ['required', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'paid_at' => ['nullable', 'date_format:Y-m-d'],
            'origin' => ['nullable', Rule::in(['manual', 'ai'])],
            'receipt_photo' => ['nullable', 'string', 'regex:/^receipts\/[A-Za-z0-9]+\.[a-z]+$/'],
        ];
    }

    public function messages(): array
    {
        return ['receipt_photo.regex' => 'Foto de comprovante inválida. Leia o comprovante de novo.'];
    }
}
