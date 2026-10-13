<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_is_varied_and_reseeding_preserves_edits(): void
    {
        config(['seeding.password' => 'test-only-demo-password']);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('products', 24);
        $this->assertSame(3, Product::distinct()->count('category'));
        $this->assertTrue(Product::where('stock', 0)->exists());
        $this->assertTrue(Product::where('stock', '>', 0)->exists());
        $this->assertTrue(Product::where('price', '<', 50)->exists());
        $this->assertTrue(Product::where('price', '>', 100)->exists());
        $user = User::where('email', 'dev@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('test-only-demo-password', $user->password));

        Product::firstOrFail()->update(['stock' => 99]);
        $hash = $user->password;
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('products', 24);
        $this->assertDatabaseCount('users', 3);
        $this->assertSame(99, Product::firstOrFail()->stock);
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_product_factory_supports_out_of_stock_products(): void
    {
        $product = Product::factory()->outOfStock()->create();

        $this->assertSame(0, $product->stock);
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $product->price);
    }
}
