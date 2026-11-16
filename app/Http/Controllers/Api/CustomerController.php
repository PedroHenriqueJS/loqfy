<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CnpjLookupUnavailable;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CnpjLookup;
use App\Support\Cnpj;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CustomerResource::collection(Customer::withCount('quotes', 'rentals')->orderBy('name')->get());
    }

    public function store(CustomerRequest $request): CustomerResource
    {
        return new CustomerResource(Customer::create($request->attributesFor(null)));
    }

    public function update(CustomerRequest $request, Customer $customer): CustomerResource
    {
        $customer->update($request->attributesFor($customer));

        return new CustomerResource($customer);
    }

    public function destroy(Customer $customer): array
    {
        $customer->delete();

        return ['deleted' => true];
    }

    public function cnpj(string $cnpj, CnpjLookup $lookup): array
    {
        abort_unless(Cnpj::isValid($cnpj), 422, 'CNPJ inválido. Confira os números.');

        try {
            $company = $lookup->find($cnpj);
        } catch (CnpjLookupUnavailable) {
            abort(503, 'A consulta de CNPJ está fora do ar. Preencha os dados à mão.');
        }

        abort_if($company === null, 404, 'CNPJ não encontrado na Receita Federal.');

        return $company;
    }
}
