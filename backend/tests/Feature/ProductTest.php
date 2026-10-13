<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_preserves_price_precision_and_fields(): void
    {
        $product = Product::create([
            'name' => 'Camiseta de teste',
            'description' => 'Produto usado para verificar persistência.',
            'image' => 'https://example.com/camiseta.jpg',
            'price' => '12345678.90',
            'category' => 'Camisetas',
            'stock' => 12,
        ])->fresh();

        $this->assertSame('12345678.90', $product->price);
        $this->assertSame(12, $product->stock);
        $this->assertSame('Camiseta de teste', $product->name);
        $this->assertSame('Produto usado para verificar persistência.', $product->description);
        $this->assertSame('https://example.com/camiseta.jpg', $product->image);
        $this->assertSame('Camisetas', $product->category);
        $this->assertNotNull($product->created_at);
        $this->assertNotNull($product->updated_at);
    }

    public function test_product_can_start_without_image_or_stock(): void
    {
        $product = Product::create([
            'name' => 'Produto sem estoque',
            'description' => 'Aguardando reposição.',
            'price' => '0.00',
            'category' => 'Camisetas',
        ])->fresh();

        $this->assertNull($product->image);
        $this->assertSame(0, $product->stock);
        $this->assertSame('0.00', $product->price);
    }

    public function test_mysql_rejects_negative_stock(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('A restrição UNSIGNED deve ser verificada no MySQL.');
        }

        $this->expectException(QueryException::class);
        $this->expectExceptionCode('22003');

        Product::create([
            'name' => 'Estoque inválido',
            'description' => 'Não deve ser persistido.',
            'price' => '10.00',
            'category' => 'Camisetas',
            'stock' => -1,
        ]);
    }
}
