<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_combine_and_survive_pagination(): void
    {
        foreach ([20, 30, 40, 50, 60] as $price) {
            Product::factory()->create(['name' => 'Camiseta Lua', 'category' => 'Camisetas', 'stock' => 3, 'price' => $price]);
        }
        Product::factory()->create(['name' => 'Camiseta Lua', 'category' => 'Camisetas', 'stock' => 0, 'price' => 40]);
        Product::factory()->create(['name' => 'Camiseta Lua', 'category' => 'Outra', 'stock' => 3, 'price' => 40]);
        Product::factory()->create(['name' => 'Sol', 'category' => 'Camisetas', 'stock' => 3, 'price' => 40]);
        $query = http_build_query(['search' => 'Lua', 'category' => 'Camisetas', 'min_price' => 30, 'max_price' => 50, 'in_stock' => 1, 'per_page' => 2, 'sort' => 'price', 'direction' => 'desc']);
        $first = $this->getJson('/api/products?'.$query)->assertOk()->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.price', '50.00')->assertJsonPath('data.1.price', '40.00');
        $next = $first->json('links.next');
        $this->assertStringContainsString('search=Lua', $next);
        $this->getJson($next)->assertOk()->assertJsonPath('meta.total', 3)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.price', '30.00');
    }

    public function test_category_and_update_sorting_cover_all_pages_and_break_ties_by_id(): void
    {
        $b = Product::factory()->create(['category' => 'B', 'updated_at' => '2026-01-03 12:00:00']);
        $a1 = Product::factory()->create(['category' => 'A', 'updated_at' => '2026-01-01 12:00:00']);
        $a2 = Product::factory()->create(['category' => 'A', 'updated_at' => '2026-01-03 12:00:00']);

        foreach ([
            ['category', 'asc', [$a1->id, $a2->id, $b->id]],
            ['category', 'desc', [$b->id, $a2->id, $a1->id]],
            ['updated_at', 'asc', [$a1->id, $b->id, $a2->id]],
            ['updated_at', 'desc', [$a2->id, $b->id, $a1->id]],
        ] as [$sort, $direction, $ids]) {
            $first = $this->getJson("/api/products?sort={$sort}&direction={$direction}&per_page=2")
                ->assertOk()->assertJsonPath('meta.total', 3)
                ->assertJsonPath('data.0.id', $ids[0])->assertJsonPath('data.1.id', $ids[1]);
            $this->getJson($first->json('links.next'))->assertOk()
                ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ids[2]);
        }
    }

    public function test_invalid_parameters_are_rejected(): void
    {
        foreach (['per_page=101', 'per_page=0', 'page=0', 'page=abc', 'sort=password', 'direction=drop', 'min_price=-1', 'max_price=1.234', 'min_price=20&max_price=10', 'in_stock=maybe', 'search[]=x', 'sort[]=id'] as $query) {
            $this->getJson('/api/products?'.$query)->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');
        }
    }

    public function test_search_escapes_wildcards_and_stock_zero_does_not_filter(): void
    {
        Product::factory()->create(['name' => '100%_algodão!', 'stock' => 0]);
        Product::factory()->create(['name' => '100 outros', 'stock' => 2]);
        $this->getJson('/api/products?'.http_build_query(['search' => '%_', 'in_stock' => 0]))
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.stock', 0);
        $this->getJson('/api/products?search=inexistente')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_price_ties_use_id_and_zero_price_is_a_real_filter(): void
    {
        $products = Product::factory()->count(3)->create(['price' => '0.00']);
        Product::factory()->create(['price' => '1.00']);
        $this->getJson('/api/products?min_price=0&max_price=0&sort=price&direction=desc&per_page=2')
            ->assertOk()->assertJsonPath('meta.total', 3)->assertJsonPath('data.0.id', $products[2]->id)
            ->assertJsonPath('data.1.id', $products[1]->id);
    }
}
