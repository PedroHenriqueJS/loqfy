<?php

namespace App\Services;

use App\Exceptions\CnpjLookupUnavailable;
use App\Support\Cnpj;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class CnpjLookup
{
    public function find(string $cnpj): ?array
    {
        $cnpj = Cnpj::digits($cnpj);

        try {
            $data = Cache::remember("cnpj:{$cnpj}", now()->addDay(), function () use ($cnpj) {
                $response = Http::timeout(10)->acceptJson()->get("https://brasilapi.com.br/api/cnpj/v1/{$cnpj}");

                if ($response->status() === 404) {
                    return ['not_found' => true];
                }

                return $response->throw()->json();
            });
        } catch (Throwable $e) {
            throw new CnpjLookupUnavailable($e->getMessage(), previous: $e);
        }

        if ($data['not_found'] ?? false) {
            return null;
        }

        return [
            'cnpj' => $cnpj,
            'legal_name' => $data['razao_social'] ?? null,
            'trade_name' => ($data['nome_fantasia'] ?? '') ?: null,
            'status' => $data['descricao_situacao_cadastral'] ?? null,
            'address' => $this->address($data),
            'phone' => $this->phone($data['ddd_telefone_1'] ?? null),
            'email' => isset($data['email']) && $data['email'] ? strtolower($data['email']) : null,
        ];
    }

    private function address(array $data): ?string
    {
        $address = collect([
            trim(($data['descricao_tipo_de_logradouro'] ?? '').' '.($data['logradouro'] ?? '')),
            $data['numero'] ?? null,
            $data['complemento'] ?? null,
            $data['bairro'] ?? null,
            trim(($data['municipio'] ?? '').(isset($data['uf']) ? '/'.$data['uf'] : '')),
        ])->map(fn ($part) => is_string($part) ? trim($part) : $part)->filter()->implode(', ');

        return $address ?: null;
    }

    private function phone(?string $value): ?string
    {
        $phone = Cnpj::digits($value);

        return strlen($phone) >= 10 ? sprintf('(%s) %s-%s', substr($phone, 0, 2), substr($phone, 2, -4), substr($phone, -4)) : null;
    }
}
