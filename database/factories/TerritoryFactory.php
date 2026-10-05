<?php

namespace Database\Factories;

use App\Models\Territory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Territory>
 */
class TerritoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake('id_ID')->city(),
        ];
    }
}
