<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductDemoVariantsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Variações demonstrativas disponíveis somente em local/testing.');
        }
        Product::whereNotNull('import_key')->where('demo_variants', false)->whereDoesntHave('variants')->select('id')->chunkById(100, function ($products): void {
            foreach ($products as $candidate) {
                DB::transaction(function () use ($candidate): void {
                    $product = Product::lockForUpdate()->findOrFail($candidate->id);
                    if ($product->variants()->exists()) {
                        return;
                    }
                    $sizes = in_array(mb_strtolower($product->category), ['camiseta', 'camisa', 'camisetas', 'camisas'], true)
                        ? ['P', 'M', 'G'] : ['ÚNICO'];
                    foreach ($sizes as $size) {
                        $product->variants()->create(['color' => 'COR DA IMAGEM', 'size' => $size, 'stock' => 5]);
                    }
                    $product->forceFill(['stock' => count($sizes) * 5, 'demo_variants' => true])->save();
                });
            }
        });
    }
}
