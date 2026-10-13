<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_boundaries_cannot_change_persisted_values(): void
    {
        $product = Product::factory()->create(['price' => '10.00', 'stock' => 5]);
        $this->actingAs(User::factory()->create(), 'web');
        foreach ([['price' => '-0.01'], ['price' => '100000000.00'], ['price' => '0.001'], ['stock' => 4294967296], ['stock' => 1.5], ['name' => str_repeat('x', 256)], ['category' => str_repeat('x', 101)], ['image' => 'ftp://example.com/image.png']] as $payload) {
            $this->patchJson('/api/products/'.$product->id, $payload)->assertUnprocessable();
            $this->assertSame('10.00', $product->fresh()->price);
            $this->assertSame(5, $product->fresh()->stock);
        }
    }

    public function test_maximum_values_and_nullable_image_are_accepted(): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->create(), 'web');
        $this->patchJson('/api/products/'.$product->id, ['price' => '99999999.99', 'stock' => 4294967295, 'image' => null])
            ->assertOk()->assertJsonPath('data.price', '99999999.99')->assertJsonPath('data.stock', 4294967295)->assertJsonPath('data.image', null);
    }
}
