<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    protected $model = Opportunity::class;

    public function definition(): array
    {
        $titles = [
            'Medical Equipment Procurement',
            'Surgical Supply Tender',
            'Diagnostic Imaging Service Contract',
            'Patient Monitoring System Upgrade',
            'Lab Equipment Maintenance',
            'Healthcare IT Implementation',
            'Pharmaceutical Distribution Partnership',
            'Emergency Room Refurbishment',
        ];

        return [
            'title' => fake()->randomElement($titles).' - '.fake()->city(),
            'customer_name' => fake('id_ID')->company(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake('id_ID')->phoneNumber(),
            'stage' => fake()->randomElement(['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost']),
            'source' => fake()->randomElement(['website', 'referral', 'cold_call', 'trade_show', 'partner', 'other']),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'estimated_value' => $value = fake()->numberBetween(10000000, 1000000000),
            'estimated_revenue' => $value * rand(80, 100) / 100,
            'estimated_completion_date' => fake()->dateTimeBetween('now', '+8 months'),
            'notes' => fake()->paragraph(),
            'customer_id' => fake()->boolean(30) ? Customer::factory() : null,
            'assigned_to' => User::factory(),
            'position' => (string) fake()->randomFloat(2, 0, 100),
        ];
    }

    public function stage(string $stage): static
    {
        return $this->state(fn () => ['stage' => $stage]);
    }
}
