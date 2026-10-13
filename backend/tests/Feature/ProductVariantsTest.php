<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_variants_are_saved_atomically_with_stock_sum_and_stable_ids(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'web');
        $product = Product::factory()->create(['stock' => 25]);
        $payload = ['variants' => [
            ['color' => 'azul', 'size' => 'p', 'stock' => 3],
            ['color' => 'azul', 'size' => 'm', 'stock' => 7],
        ]];
        $first = $this->patchJson('/api/products/'.$product->id, $payload)
            ->assertOk()->assertJsonPath('data.stock', 10)->assertJsonPath('data.variants.0.color', 'AZUL');
        $id = $first->json('data.variants.0.id');
        $payload['variants'][0]['stock'] = 5;
        $this->patchJson('/api/products/'.$product->id, $payload)->assertOk()
            ->assertJsonPath('data.stock', 12)->assertJsonPath('data.variants.0.id', $id);
        $this->patchJson('/api/products/'.$product->id, ['stock' => 999])->assertOk()->assertJsonPath('data.stock', 12);
        $this->getJson('/api/products/'.$product->id)->assertOk()->assertJsonCount(2, 'data.variants');
        $this->getJson('/api/products?in_stock=1')->assertOk()->assertJsonPath('meta.total', 1);
        $this->patchJson('/api/products/'.$product->id, ['variants' => []])->assertOk()->assertJsonPath('data.stock', 0);
        $this->assertDatabaseCount('product_variants', 0);
    }

    public function test_duplicate_or_incomplete_combinations_do_not_change_product(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'web');
        $product = Product::factory()->create(['stock' => 25, 'name' => 'Original']);
        foreach ([
            [['color' => ' Azul ', 'size' => 'p', 'stock' => 3], ['color' => 'AZUL', 'size' => 'P', 'stock' => 1]],
            [['color' => '', 'size' => 'P', 'stock' => 1]],
            [['color' => 'AZUL', 'size' => '', 'stock' => 1]],
            [['color' => 'AZUL', 'size' => 'P', 'stock' => -1]],
            [['color' => 'AZUL', 'size' => 'P', 'stock' => 1000001]],
        ] as $variants) {
            $this->patchJson('/api/products/'.$product->id, ['name' => 'Changed', 'variants' => $variants])
                ->assertUnprocessable();
        }
        $this->assertSame(25, $product->fresh()->stock);
        $this->assertSame('Original', $product->fresh()->name);
        $this->assertDatabaseCount('product_variants', 0);
    }

    public function test_creation_and_deletion_and_guest_permissions(): void
    {
        $product = Product::factory()->create(['stock' => 4]);
        $this->patchJson('/api/products/'.$product->id, ['variants' => []])->assertUnauthorized();
        $this->getJson('/api/products/'.$product->id)->assertOk()->assertJsonCount(0, 'data.variants');
        $this->assertSame(4, $product->fresh()->stock);
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'web');
        $response = $this->postJson('/api/products', [
            'name' => 'New', 'description' => 'Description', 'price' => '19.90', 'category' => 'Shirts',
            'variants' => [['color' => 'BLACK', 'size' => 'L', 'stock' => 2]],
        ])->assertCreated()->assertJsonPath('data.stock', 2)->assertJsonCount(1, 'data.variants');
        $this->deleteJson('/api/products/'.$response->json('data.id'))->assertNoContent();
        $this->assertDatabaseCount('product_variants', 0);
    }
}
