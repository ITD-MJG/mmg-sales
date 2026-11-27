<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentStatus;
use App\Models\Territory;
use App\Models\User;
use App\Services\Reports\SalesRevenueByInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function paidOrder(User $rep, float $total, array $payments, array $attributes = []): Order
{
    $order = Order::factory()->create([
        'sales_id' => $rep->id,
        'total_amount' => $total,
        'order_date' => '2026-03-10',
        ...$attributes,
    ]);

    foreach ($payments as $payment) {
        PaymentStatus::create([
            'order_id' => $order->id,
            'status' => $payment['status'],
            'amount' => $payment['amount'],
            'effective_date' => $payment['effective_date'] ?? '2026-03-15',
        ]);
    }

    return $order;
}

test('only partially or fully paid orders count', function () {
    $rep = User::factory()->create();
    paidOrder($rep, 1000, [['status' => 'full', 'amount' => 1000]]);
    paidOrder($rep, 2000, [['status' => 'pending', 'amount' => 0]]);
    $unpaid = Order::factory()->create([
        'sales_id' => $rep->id,
        'total_amount' => 5000,
        'order_date' => '2026-03-11',
    ]);

    $service = new SalesRevenueByInvoiceService(
        Carbon::parse('2026-03-01'),
        Carbon::parse('2026-03-31'),
        $rep,
    );

    $rows = $service->breakdown('sales_rep', 'total');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['value'])->toEqual(1000.0)
        ->and($unpaid->exists)->toBeTrue();
});

test('territory groups by the sales reps territory', function () {
    $territory = Territory::factory()->create(['name' => 'West']);
    $rep = User::factory()->create(['territory_id' => $territory->id]);
    paidOrder($rep, 1000, [['status' => 'partial', 'amount' => 400]]);

    $rows = (new SalesRevenueByInvoiceService(
        Carbon::parse('2026-03-01'),
        Carbon::parse('2026-03-31'),
        $rep,
    ))->breakdown('territory', 'total');

    expect($rows[0])->toMatchArray(['key' => (string) $territory->id, 'label' => 'West', 'value' => 1000.0]);
});

test('paid metric sums partial and full payment rows', function () {
    $rep = User::factory()->create();
    paidOrder($rep, 1000, [
        ['status' => 'partial', 'amount' => 400],
        ['status' => 'full', 'amount' => 600],
    ]);

    $service = new SalesRevenueByInvoiceService(
        Carbon::parse('2026-03-01'),
        Carbon::parse('2026-03-31'),
        $rep,
    );

    expect($service->breakdown('sales_rep', 'paid')[0]['value'])->toEqual(1000.0);
});

test('customer groups by end customer', function () {
    $rep = User::factory()->create();
    $acme = Customer::factory()->create(['name' => 'Acme']);
    $beta = Customer::factory()->create(['name' => 'Beta']);
    paidOrder($rep, 1000, [['status' => 'full', 'amount' => 1000]], ['end_customer_id' => $acme->id]);
    paidOrder($rep, 500, [['status' => 'full', 'amount' => 500]], ['end_customer_id' => $beta->id]);

    $rows = (new SalesRevenueByInvoiceService(
        Carbon::parse('2026-03-01'),
        Carbon::parse('2026-03-31'),
        $rep,
    ))->breakdown('customer', 'total');

    expect(collect($rows)->pluck('value', 'label')->all())
        ->toMatchArray(['Acme' => 1000.0, 'Beta' => 500.0]);
});
