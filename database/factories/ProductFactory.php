<?php

namespace Database\Factories;

use App\Models\Principal;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'internal_code' => fake()->unique()->bothify('PRD-TD-######'),
            'principal_id' => Principal::factory(),
            'category' => fake()->randomElement(['medical_equipment', 'pharmaceutical', 'consumables', 'diagnostics', 'other']),
            'description' => fake()->paragraph(),
            'unit_price' => fake()->numberBetween(5000, 5000000),
            'ecatalog_price' => fake()->randomElement([fake()->numberBetween(5000, 5000000), null]),
            'unit_of_measure' => fake()->randomElement(['Box', 'Pcs', 'Vial', 'Bottle']),
            'is_active' => true,
        ];
    }
}
