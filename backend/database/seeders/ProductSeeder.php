<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $themes = ['Horizonte', 'Café', 'Montanha', 'Mar', 'Lua', 'Floresta', 'Sol', 'Cidade'];

        foreach (['Camisetas', 'Canecas', 'Ecobags'] as $categoryIndex => $category) {
            foreach ($themes as $index => $theme) {
                // Nomes estáveis permitem repetir a carga sem substituir edições locais.
                Product::firstOrCreate(['name' => $category.' — '.$theme], [
                    'description' => 'Produto fictício da coleção '.$theme.' para demonstração do catálogo.',
                    'image' => null,
                    'price' => sprintf('%d.90', 19 + $categoryIndex * 30 + $index * 10),
                    'category' => $category,
                    'stock' => $index % 3 === 0 ? 0 : ($index + 1) * 4,
                ]);
            }
        }
    }
}
