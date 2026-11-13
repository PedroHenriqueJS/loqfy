<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Support\Cnpj;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_cnpj_check_digits(): void
    {
        $this->assertTrue(Cnpj::isValid('48.594.624/0001-00'));
        $this->assertFalse(Cnpj::isValid('48.594.624/0001-01'));
        $this->assertFalse(Cnpj::isValid('11.111.111/1111-11'));
        $this->assertFalse(Cnpj::isValid('123'));
    }

    public function test_lookup_maps_the_registry_answer_and_rejects_typos_before_calling_out(): void
    {
        Http::fake(['brasilapi.com.br/*' => Http::response([
            'razao_social' => 'EMPRESA EXEMPLO LTDA',
            'nome_fantasia' => 'EXEMPLO',
            'descricao_situacao_cadastral' => 'ATIVA',
            'descricao_tipo_de_logradouro' => 'RUA',
            'logradouro' => 'DAS FLORES',
            'numero' => '10',
            'complemento' => '',
            'bairro' => 'CENTRO',
            'municipio' => 'FLORIANOPOLIS',
            'uf' => 'SC',
            'ddd_telefone_1' => '4833334444',
            'email' => 'CONTATO@EXEMPLO.COM',
        ])]);

        $this->getJson('/api/cnpj/48594624000101')->assertStatus(422);
        Http::assertNothingSent();

        $this->getJson('/api/cnpj/48594624000100')->assertOk()->assertJson([
            'cnpj' => '48594624000100',
            'legal_name' => 'EMPRESA EXEMPLO LTDA',
            'status' => 'ATIVA',
            'address' => 'RUA DAS FLORES, 10, CENTRO, FLORIANOPOLIS/SC',
            'phone' => '(48) 3333-4444',
            'email' => 'contato@exemplo.com',
        ]);
    }

    public function test_the_document_check_is_dated_kept_and_cleared(): void
    {
        $payload = ['name' => 'Renata Souza', 'kind' => 'person', 'cnpj' => '48594624000100', 'document_verified' => true];

        $id = $this->postJson('/api/customers', $payload)->assertCreated()->json('id');
        $customer = Customer::find($id);
        $this->assertNotNull($customer->document_verified_at);
        $this->assertNull($customer->cnpj);

        $checkedAt = $customer->document_verified_at->toIso8601String();
        $this->travel(2)->days();
        $this->putJson("/api/customers/{$id}", $payload)->assertOk();
        $this->assertSame($checkedAt, $customer->fresh()->document_verified_at->toIso8601String());

        $this->putJson("/api/customers/{$id}", ['document_verified' => false] + $payload)->assertOk();
        $this->assertNull($customer->fresh()->document_verified_at);
    }
}
