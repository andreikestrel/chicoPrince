<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BootstrapCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['seeding.password' => 'test-only-password-12345', 'seeding.email' => 'bootstrap@example.test']);
    }

    public function test_offline_fallback_and_reexecution_preserve_products(): void
    {
        Process::fake();
        $this->artisan('catalog:bootstrap', ['--offline' => true])->assertSuccessful();
        $this->assertDatabaseCount('products', 24);
        $product = Product::first();
        $product->update(['name' => 'Edição humana']);
        $this->artisan('catalog:bootstrap')->assertSuccessful();
        $this->assertDatabaseCount('products', 24);
        $this->assertSame('Edição humana', $product->fresh()->name);
        Process::assertNothingRan();
    }

    public function test_valid_sample_imports_only_collected_products_with_demo_variants(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'catalog-');
        try {
            file_put_contents($file, json_encode([$this->sample(), $this->sample()]));
            $this->artisan('catalog:bootstrap', ['--file' => $file])->assertSuccessful();
            $this->assertDatabaseCount('products', 1);
            $this->assertDatabaseCount('users', 3);
            $this->assertDatabaseHas('products', ['name' => 'Amostra', 'stock' => 15, 'price' => '39.90']);
            $this->assertDatabaseCount('product_variants', 3);
        } finally {
            unlink($file);
        }
    }

    public function test_invalid_sample_falls_back_without_partial_import(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'catalog-');
        try {
            file_put_contents($file, '{"invalid":true}');
            $this->artisan('catalog:bootstrap', ['--file' => $file])->assertSuccessful();
            $this->assertDatabaseCount('products', 24);
        } finally {
            unlink($file);
        }
    }

    public function test_collector_failure_uses_fallback(): void
    {
        Process::fake(['*' => Process::result(exitCode: 1)]);
        $this->artisan('catalog:bootstrap')->assertSuccessful();
        $this->assertDatabaseCount('products', 24);
    }

    public function test_rejects_invalid_options_before_mutation(): void
    {
        $this->artisan('catalog:bootstrap', ['--limit' => 46])->assertFailed();
        $this->artisan('catalog:bootstrap', ['--limit' => 0])->assertFailed();
        $this->artisan('catalog:bootstrap', ['--offline' => true, '--file' => 'x'])->assertFailed();
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_replacement_preserves_edits_custom_products_and_existing_variants(): void
    {
        Storage::fake('local');
        $this->seed(ProductSeeder::class);
        $rows = Product::orderBy('id')->get();
        $rows[0]->update(['name' => 'Editado pelo humano']);
        $rows[1]->update(['stock' => 123]);
        $rows[2]->variants()->create(['color' => 'AZUL', 'size' => 'M', 'stock' => 7]);
        $custom = Product::factory()->create(['name' => 'Produto humano']);
        $file = tempnam(sys_get_temp_dir(), 'catalog-');
        try {
            file_put_contents($file, json_encode([$this->sample()]));
            $this->artisan('catalog:bootstrap', ['--file' => $file, '--replace-demo' => true, '--dry-run' => true])->assertSuccessful();
            $this->assertDatabaseCount('products', 25);
            $this->assertDatabaseCount('users', 0);
            $this->artisan('catalog:bootstrap', ['--file' => $file, '--replace-demo' => true])->assertSuccessful();
            $this->assertDatabaseCount('products', 5);
            $this->assertTrue($custom->fresh()->exists);
            $this->assertSame('Editado pelo humano', $rows[0]->fresh()->name);
            $this->assertSame(123, $rows[1]->fresh()->stock);
            $this->assertSame(7, $rows[2]->variants()->first()->stock);
            $this->assertCount(1, Storage::disk('local')->allFiles('catalog-backups'));
            $imported = Product::whereNotNull('import_key')->firstOrFail();
            $imported->update(['name' => 'Importado editado']);
            $imported->variants()->first()->update(['stock' => 2]);
            $this->artisan('catalog:bootstrap', ['--file' => $file, '--replace-demo' => true])->assertSuccessful();
            $this->assertDatabaseCount('products', 5);
            $this->assertSame('Importado editado', $imported->fresh()->name);
            $this->assertSame(2, $imported->variants()->first()->stock);
        } finally {
            unlink($file);
        }
    }

    public function test_invalid_replacement_preserves_current_catalog(): void
    {
        $this->seed(ProductSeeder::class);
        $this->artisan('catalog:bootstrap', ['--file' => '/nonexistent-test-sample.json', '--replace-demo' => true])->assertSuccessful();
        $this->assertDatabaseCount('products', 24);
        $this->assertDatabaseCount('product_variants', 0);
    }

    private function sample(): array
    {
        return [
            'source' => 'chicorei', 'source_url' => 'https://chicorei.com/camiseta/amostra-1.html',
            'name' => 'Amostra', 'description' => 'Dados fictícios para teste isolado.',
            'price' => '39.90', 'currency' => 'BRL', 'image_url' => 'https://example.test/image.jpg',
            'category_path' => ['Roupas', 'Camiseta', 'Arte'], 'stock' => 999,
        ];
    }
}
