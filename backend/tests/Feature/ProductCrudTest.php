<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['name' => 'Camiseta', 'description' => 'Descrição', 'price' => '59.90', 'category' => 'Camisetas'];
    }

    public function test_guests_can_list_and_view_but_cannot_write(): void
    {
        $product = Product::factory()->create();
        $this->getJson('/api/products')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/products/'.$product->id)->assertOk()->assertJsonPath('data.id', $product->id);
        $this->postJson('/api/products', $this->payload())->assertUnauthorized();
        $this->patchJson('/api/products/'.$product->id, ['stock' => 0])->assertUnauthorized();
        $this->putJson('/api/products/'.$product->id, $this->payload())->assertUnauthorized();
        $this->deleteJson('/api/products/'.$product->id)->assertUnauthorized();
        $this->assertDatabaseCount('products', 1);
    }

    public function test_authenticated_user_can_create_patch_replace_and_delete(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'web');
        $created = $this->postJson('/api/products', $this->payload() + ['id' => 999, 'unexpected' => 'ignored'])
            ->assertCreated()->assertJsonPath('data.stock', 0)->assertJsonPath('data.image', null)
            ->assertJsonPath('data.price', '59.90');
        $id = $created->json('data.id');
        $this->assertNotSame(999, $id);
        $this->patchJson('/api/products/'.$id, ['stock' => 5])->assertOk()
            ->assertJsonPath('data.name', 'Camiseta')->assertJsonPath('data.stock', 5);
        $this->putJson('/api/products/'.$id, $this->payload() + ['stock' => 2, 'image' => 'https://example.com/item.jpg'])
            ->assertOk()->assertJsonPath('data.stock', 2);
        $this->deleteJson('/api/products/'.$id)->assertNoContent();
        $this->getJson('/api/products/'.$id)->assertNotFound()->assertJsonPath('error.code', 'not_found');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_invalid_values_do_not_modify_product(): void
    {
        $product = Product::factory()->create(['stock' => 4, 'price' => '10.00']);
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'web');
        $this->patchJson('/api/products/'.$product->id, ['stock' => -1, 'price' => '1.234', 'image' => 'javascript:alert(1)', 'name' => ''])
            ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['stock', 'price', 'image', 'name']]]);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertSame('10.00', $product->fresh()->price);
        $this->putJson('/api/products/'.$product->id, ['stock' => 1])->assertUnprocessable();
    }

    public function test_missing_products_return_404_and_lists_are_paginated(): void
    {
        Product::factory()->count(16)->create();
        $this->getJson('/api/products')->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('meta.total', 16);
        $this->getJson('/api/products?page=2')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'web');
        $this->patchJson('/api/products/999999', ['stock' => 1])->assertNotFound();
        $this->deleteJson('/api/products/999999')->assertNotFound();
    }
}
