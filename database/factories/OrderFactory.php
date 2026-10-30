<?php

namespace Database\Factories;

use App\Models\Customer;

use App\Models\Distributor;
use App\Models\Order;

use App\Models\Principal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(1000000, 100000000);
        $tax = $subtotal * 0.11;
        $discount = fake()->numberBetween(0, 5000000);
        $total = $subtotal + $tax - $discount;

        return [
            'tahun' => now()->year,
            'bulan' => now()->month,
            'sales_id' => User::factory(),
            'end_customer_id' => Customer::factory(),
            'principal_id' => Principal::factory(),
            'reg_inst' => fake()->randomElements(['REG', 'INST', 'Consumable'], random_int(1, 2)),
            'sales_type_id' => fake()->randomElement(['INAPROC', 'non-INAPROC']),
            'net_sales_total' => $total,
            'jual_kso' => fake()->randomElement(['Jual', 'KSO']),
            'distributor_id' => Distributor::factory(),
            'opportunity_id' => null,
            'discount_on' => fake()->randomFloat(2, 0, 20),
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'discount_amount' => $discount,
            'total_amount' => $total,
            'order_date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'payment_method' => fake()->randomElement(['Transfer', 'Credit Card', 'Cash']),
            'created_by' => User::factory(),
        ];
    }
}
