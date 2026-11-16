<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Support\Cnpj;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['cnpj' => Cnpj::digits($this->input('cnpj')) ?: null]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in([Customer::PERSON, Customer::COMPANY])],
            'cnpj' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail) {
                if (! Cnpj::isValid($value)) {
                    $fail('CNPJ inválido. Confira os números.');
                }
            }],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
            'document_verified' => ['required', 'boolean'],
        ];
    }

    public function attributesFor(?Customer $customer): array
    {
        $data = $this->validated();

        if ($data['kind'] === Customer::PERSON) {
            $data['cnpj'] = null;
        }

        $data['document_verified_at'] = $data['document_verified'] ? ($customer?->document_verified_at ?? now()) : null;
        unset($data['document_verified']);

        return $data;
    }
}
