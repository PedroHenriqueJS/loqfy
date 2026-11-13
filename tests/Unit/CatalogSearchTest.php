<?php

namespace Tests\Unit;

use App\Services\CatalogSearch;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogSearchTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('terms')]
    public function test_search(string $term, array $expected): void
    {
        $this->seed(CatalogSeeder::class);

        $this->assertSame($expected, app(CatalogSearch::class)->search($term, 3)->pluck('name')->all());
    }

    public static function terms(): array
    {
        return [
            'generic term returns every size' => ['betoneira', ['Betoneira 400L', 'Betoneira 150L']],
            'size narrows it down' => ['betonera 400 litros', ['Betoneira 400L']],
            'typo' => ['furadera', ['Furadeira de impacto']],
            'plural' => ['escadas extensíveis', ['Escada extensível 7m']],
            'slang' => ['makita', ['Serra mármore']],
            'alias' => ['lava jato', ['Lavadora de alta pressão']],
            'not in catalog' => ['retroescavadeira', []],
            'short common words do not match' => ['andaime', ['Andaime tubular 1,0 x 1,5m']],
        ];
    }
}
