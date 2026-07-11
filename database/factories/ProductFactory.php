<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(rand(2, 4), true);
        $price = fake()->randomFloat(2, 5000, 120000);
        $hasDiscount = fake()->boolean(35);

        return [
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'name' => ucfirst($name),
            'slug' => Str::slug($name.'-'.fake()->unique()->numerify('###')),
            'description' => fake()->paragraphs(2, true),
            'short_description' => fake()->sentence(10),
            'sku' => strtoupper(fake()->unique()->bothify('??-####')),
            'price' => $price,
            'compare_price' => $hasDiscount ? round($price * 1.2, 2) : null,
            'stock' => fake()->numberBetween(0, 80),
            'is_active' => fake()->boolean(92),
            'is_featured' => fake()->boolean(20),
            'attributes' => [
                'color' => fake()->randomElement(['Negro', 'Blanco', 'Dorado', 'Gris', 'Azul marino']),
                'talla' => fake()->optional()->randomElement(['S', 'M', 'L', 'XL', 'Única']),
            ],
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product) {
            $imageCount = fake()->numberBetween(1, 2);

            for ($i = 0; $i < $imageCount; $i++) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => "https://picsum.photos/seed/product-{$product->id}-{$i}/800/800",
                    'is_primary' => $i === 0,
                    'sort_order' => $i,
                ]);
            }
        });
    }
}
