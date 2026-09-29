<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake('id_ID')->company().' '.fake()->randomElement(['Hospital', 'Clinic', 'Pharmacy', 'Lab']),
            'type' => fake()->randomElement(['hospital_clinic', 'pt_cv', 'other']),
            'tax_number' => fake()->unique()->numerify('##.###.###.#-###.###'),
            'address' => fake('id_ID')->address(),
            'city' => fake('id_ID')->city(),
            'state' => fake('id_ID')->state(),
            'postal_code' => fake('id_ID')->postcode(),
            'country' => 'Indonesia',
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake('id_ID')->phoneNumber(),
            'website' => fake()->url(),
            'is_active' => true,
        ];
    }
}
