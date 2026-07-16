<?php

namespace Database\Factories;

use App\Models\Decant;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Decant>
 */
class DecantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'ml' => fake()->randomElement([3, 5, 10, 15]),
            'price' => fake()->randomFloat(2, 5, 50),
            'stock' => fake()->numberBetween(0, 50),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
