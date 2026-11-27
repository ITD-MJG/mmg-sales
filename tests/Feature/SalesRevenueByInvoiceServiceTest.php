<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentStatus;
use App\Models\Principal;
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

test('sales supervisor groups by the reps manager', function () {
    $manager = User::factory()->create(['name' => 'Boss']);
    $rep = User::factory()->create(['manager_id' => $manager->id]);
    paidOrder($rep, 1000, [['status' => 'full', 'amount' => 1000]]);

    $rows = (new SalesRevenueByInvoiceService(
        Carbon::parse('2026-03-01'),
        Carbon::parse('2026-03-31'),
        $manager,
    ))->breakdown('sales_supervisor', 'total');

    expect($rows[0])->toMatchArray(['key' => (string) $manager->id, 'label' => 'Boss', 'value' => 1000.0]);
});

test('principal split follows line subtotals', function () {
    $rep = User::factory()->create();
    $a = Principal::factory()->create(['name' => 'Alpha']);
    $b = Principal::factory()->create(['name' => 'Beta']);
    $order = paidOrder($rep, 1000, [['status' => 'full', 'amount' => 1000]]);

    OrderItem::factory()->create(['order_id' => $order->id, 'principal_id' => $a->id, 'subtotal' => 600]);
    OrderItem::factory()->create(['order_id' => $order->id, 'principal_id' => $b->id, 'subtotal' => 400]);

    $rows = (new SalesRevenueByInvoiceService(
        Carbon::parse('2026-03-01'),
        Carbon::parse('2026-03-31'),
        $rep,
    ))->breakdown('principal', 'total');

    expect(collect($rows)->pluck('value', 'label')->all())
        ->toMatchArray(['Alpha' => 600.0, 'Beta' => 400.0]);
});

test('orders without line items fall back to the header principal', function () {
    $rep = User::factory()->create();
    $principal = Principal::factory()->create(['name' => 'Header']);
    paidOrder($rep, 1000, [['status' => 'full', 'amount' => 1000]], ['principal_id' => $principal->id]);

    $rows = (new SalesRevenueByInvoiceService(
        Carbon::parse('2026-03-01'),
        Carbon::parse('2026-03-31'),
        $rep,
    ))->breakdown('principal', 'total');

    expect($rows[0])->toMatchArray(['label' => 'Header', 'value' => 1000.0]);
});

test('marketing split divides a principal share among its users', function () {
    $rep = User::factory()->create();
    $a = Principal::factory()->create(['name' => 'Alpha']);
    $b = Principal::factory()->create(['name' => 'Beta']);
    $ann = User::factory()->create(['name' => 'Ann']);
    $bob = User::factory()->create(['name' => 'Bob']);
    $carl = User::factory()->create(['name' => 'Carl']);
    $a->marketingUsers()->attach([$ann->id, $bob->id]);
    $b->marketingUsers()->attach($carl->id);

    $order = paidOrder($rep, 1000, [['status' => 'partial', 'amount' => 500]]);
    OrderItem::factory()->create(['order_id' => $order->id, 'principal_id' => $a->id, 'subtotal' => 600]);
    OrderItem::factory()->create(['order_id' => $order->id, 'principal_id' => $b->id, 'subtotal' => 400]);

    $service = new SalesRevenueByInvoiceService(
        Carbon::parse('2026-03-01'),
        Carbon::parse('2026-03-31'),
        $rep,
    );

    expect(collect($service->breakdown('marketing', 'total'))->pluck('value', 'label')->all())
        ->toMatchArray(['Ann' => 300.0, 'Bob' => 300.0, 'Carl' => 400.0])
        ->and(collect($service->breakdown('marketing', 'paid'))->pluck('value', 'label')->all())
        ->toMatchArray(['Ann' => 150.0, 'Bob' => 150.0, 'Carl' => 200.0]);
});

test('a principal without marketing users attributes to unassigned marketing', function () {
    $rep = User::factory()->create();
    $principal = Principal::factory()->create(['name' => 'Solo']);
    paidOrder($rep, 1000, [['status' => 'full', 'amount' => 1000]], ['principal_id' => $principal->id]);

    $rows = (new SalesRevenueByInvoiceService(
        Carbon::parse('2026-03-01'),
        Carbon::parse('2026-03-31'),
        $rep,
    ))->breakdown('marketing', 'total');

    expect($rows[0])->toMatchArray(['label' => 'Unassigned Marketing', 'value' => 1000.0]);
});

test('invoices narrow to the selected group and carry the group share', function () {
    $rep = User::factory()->create(['name' => 'Rep One']);
    $customer = Customer::factory()->create(['name' => 'Acme']);
    $a = Principal::factory()->create(['name' => 'Alpha']);
    $ann = User::factory()->create(['name' => 'Ann']);
    $a->marketingUsers()->attach($ann->id);

    $order = paidOrder($rep, 1000, [['status' => 'partial', 'amount' => 400]], ['end_customer_id' => $customer->id]);
    OrderItem::factory()->create(['order_id' => $order->id, 'principal_id' => $a->id, 'subtotal' => 600]);

    $result = (new SalesRevenueByInvoiceService(
        Carbon::parse('2026-03-01'),
        Carbon::parse('2026-03-31'),
        $rep,
    ))->invoices('customer', 'total', (string) $customer->id);

    expect($result['count'])->toBe(1)
        ->and($result['total'])->toEqual(1000.0)
        ->and($result['rows'][0])->toMatchArray([
            'order_number' => $order->order_number,
            'customer' => 'Acme',
            'rep' => 'Rep One',
            'total' => 1000.0,
            'paid' => 400.0,
            'remaining' => 600.0,
            'share' => 1000.0,
        ]);
});

test('invoice rows stop at 25 but the count reports every match', function () {
    $rep = User::factory()->create();
    foreach (range(1, 30) as $i) {
        paidOrder($rep, 100, [['status' => 'full', 'amount' => 100]]);
    }

    $result = (new SalesRevenueByInvoiceService(
        Carbon::parse('2026-03-01'),
        Carbon::parse('2026-03-31'),
        $rep,
    ))->invoices('sales_rep', 'total', (string) $rep->id);

    expect($result['rows'])->toHaveCount(25)
        ->and($result['count'])->toBe(30);
});

test('a zero total order with a full payment still qualifies', function () {
    $rep = User::factory()->create();
    paidOrder($rep, 0, [['status' => 'full', 'amount' => 0]]);

    $rows = (new SalesRevenueByInvoiceService(
        Carbon::parse('2026-03-01'),
        Carbon::parse('2026-03-31'),
        $rep,
    ))->breakdown('sales_rep', 'total');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['value'])->toBe(0.0);
});
