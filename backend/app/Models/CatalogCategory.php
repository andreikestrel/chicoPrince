<?php

namespace App\Models;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CatalogCategory
{
    // Categorias continuam sendo os valores existentes nos produtos.
    public static function all(): Collection
    {
        $names = Product::query()->get(['category', 'category_path'])->flatMap(fn (Product $product) => array_merge([$product->category], $product->category_path ?? []))->unique()->sort()->values();
        $bases = $names->mapWithKeys(fn (string $name) => [$name => Str::slug($name) ?: 'categoria']);
        $counts = $bases->countBy();
        $reserved = ['login', 'conta', 'checkout', 'admin', 'produtos', 'api', 'carrinho'];

        return $names->map(function (string $name) use ($bases, $counts, $reserved) {
            $slug = $bases[$name];
            if ($counts[$slug] > 1 || in_array($slug, $reserved, true) || $slug === 'categoria') {
                $slug .= '-'.substr(hash('sha256', $name), 0, 10);
            }

            return ['name' => $name, 'slug' => $slug];
        })->values();
    }
}
