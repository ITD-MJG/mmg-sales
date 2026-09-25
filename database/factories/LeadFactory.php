<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'title' => fake()->randomElement([
                'Cardiology Equipment Inquiry',
                'Radiology Supply Enquiry',
                'New Clinic Setup',
                'Pharmacy Restock Request',
                'Lab Reagent Interest',
            ]).' - '.fake()->city(),
            'customer_name' => fake('id_ID')->company(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake('id_ID')->phoneNumber(),
            'status' => fake()->randomElement(['new', 'contacted']),
            'source' => fake()->randomElement(['website', 'referral', 'cold_call', 'trade_show', 'partner', 'other']),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'notes' => fake()->paragraph(),
            'customer_id' => Customer::factory(),
            'assigned_to' => User::factory(),
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function converted(): static
    {
        return $this->state(fn () => [
            'status' => 'converted',
            'converted_at' => now(),
        ]);
    }

    public function disqualified(): static
    {
        return $this->state(fn () => [
            'status' => 'disqualified',
            'disqualified_at' => now(),
        ]);
    }
}
