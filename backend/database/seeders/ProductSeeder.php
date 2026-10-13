<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public static function samples(): array
    {
        $rows = [];
        $themes = ['Horizonte', 'Café', 'Montanha', 'Mar', 'Lua', 'Floresta', 'Sol', 'Cidade'];
        foreach (['Camisetas', 'Canecas', 'Ecobags'] as $categoryIndex => $category) {
            foreach ($themes as $index => $theme) {
                $rows[] = [
                    'name' => $category.' — '.$theme,
                    'description' => 'Produto fictício da coleção '.$theme.' para demonstração do catálogo.',
                    'image' => null,
                    'price' => sprintf('%d.90', 19 + $categoryIndex * 30 + $index * 10),
                    'category' => $category,
                    'stock' => $index % 3 === 0 ? 0 : ($index + 1) * 4,
                ];
            }
        }

        return $rows;
    }

    public function run(): void
    {
        foreach (self::samples() as $row) {
            Product::firstOrCreate(['name' => $row['name']], $row);
        }
    }

    public static function pristine(Product $product): bool
    {
        if ($product->import_key !== null || $product->variants()->exists()) {
            return false;
        }
        // Mudança já salva, mesmo restaurada ao valor inicial, é preservada.
        if (! $product->created_at?->equalTo($product->updated_at)) {
            return false;
        }
        foreach (self::samples() as $row) {
            if ($product->only(array_keys($row)) === $row) {
                return true;
            }
        }

        return false;
    }
}
