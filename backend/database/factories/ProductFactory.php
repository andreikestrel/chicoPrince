<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'image' => null,
            'price' => sprintf('%d.%02d', fake()->numberBetween(10, 500), fake()->numberBetween(0, 99)),
            'category' => fake()->randomElement(['Camisetas', 'Canecas', 'Ecobags']),
            'stock' => fake()->numberBetween(0, 50),
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock' => 0]);
    }
}
