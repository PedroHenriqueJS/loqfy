<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\Cnpj;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Throwable;

class CustomerController extends Controller
{
    public function index(): Collection
    {
        return Customer::withCount('quotes', 'rentals')->orderBy('name')->get();
    }

    public function store(Request $request): Customer
    {
        return Customer::create($this->validated($request, null));
    }

    public function update(Request $request, Customer $customer): Customer
    {
        $customer->update($this->validated($request, $customer));

        return $customer;
    }

    public function destroy(Customer $customer): array
    {
        $customer->delete();

        return ['deleted' => true];
    }

    public function cnpj(string $cnpj): array
    {
        $cnpj = Cnpj::digits($cnpj);
        abort_unless(Cnpj::isValid($cnpj), 422, 'CNPJ inválido. Confira os números.');

        try {
            $data = Cache::remember("cnpj:{$cnpj}", now()->addDay(), function () use ($cnpj) {
                $response = Http::timeout(10)->acceptJson()->get("https://brasilapi.com.br/api/cnpj/v1/{$cnpj}");

                if ($response->status() === 404) {
                    return ['not_found' => true];
                }

                return $response->throw()->json();
            });
        } catch (Throwable) {
            abort(503, 'A consulta de CNPJ está fora do ar. Preencha os dados à mão.');
        }

        abort_if($data['not_found'] ?? false, 404, 'CNPJ não encontrado na Receita Federal.');

        $address = collect([
            trim(($data['descricao_tipo_de_logradouro'] ?? '').' '.($data['logradouro'] ?? '')),
            $data['numero'] ?? null,
            $data['complemento'] ?? null,
            $data['bairro'] ?? null,
            trim(($data['municipio'] ?? '').(isset($data['uf']) ? '/'.$data['uf'] : '')),
        ])->map(fn ($part) => is_string($part) ? trim($part) : $part)->filter()->implode(', ');

        $phone = Cnpj::digits($data['ddd_telefone_1'] ?? '');

        return [
            'cnpj' => $cnpj,
            'legal_name' => $data['razao_social'] ?? null,
            'trade_name' => ($data['nome_fantasia'] ?? '') ?: null,
            'status' => $data['descricao_situacao_cadastral'] ?? null,
            'address' => $address ?: null,
            'phone' => strlen($phone) >= 10 ? sprintf('(%s) %s-%s', substr($phone, 0, 2), substr($phone, 2, -4), substr($phone, -4)) : null,
            'email' => isset($data['email']) && $data['email'] ? strtolower($data['email']) : null,
        ];
    }

    private function validated(Request $request, ?Customer $customer): array
    {
        $request->merge(['cnpj' => Cnpj::digits($request->input('cnpj')) ?: null]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in([Customer::PERSON, Customer::COMPANY])],
            'cnpj' => ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if (! Cnpj::isValid($value)) {
                    $fail('CNPJ inválido. Confira os números.');
                }
            }],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
            'document_verified' => ['required', 'boolean'],
        ]);

        if ($data['kind'] === Customer::PERSON) {
            $data['cnpj'] = null;
        }

        $data['document_verified_at'] = $data['document_verified'] ? ($customer?->document_verified_at ?? now()) : null;
        unset($data['document_verified']);

        return $data;
    }
}
