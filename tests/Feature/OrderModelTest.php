<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

test('order has order items relationship', function () {
    $order = new Order;

    expect($order->orderItems())->toBeInstanceOf(HasMany::class);
});

test('order item has order relationship', function () {
    $orderItem = new OrderItem;

    expect($orderItem->order())->toBeInstanceOf(BelongsTo::class);
});

test('order has customer relationship', function () {
    $order = new Order;

    expect($order->customer())->toBeInstanceOf(BelongsTo::class);
});

test('order customer relationship works with loaded data', function () {
    $customer = Customer::factory()->create();
    $order = Order::factory()->create(['end_customer_id' => $customer->id]);

    $order->load('customer');

    expect($order->customer->id)->toBe($customer->id);
    expect($order->customer->name)->toBe($customer->name);
});

test('orders can be filtered by end_customer_id', function () {
    $customer1 = Customer::factory()->create();
    $customer2 = Customer::factory()->create();

    $order1 = Order::factory()->create(['end_customer_id' => $customer1->id]);
    $order2 = Order::factory()->create(['end_customer_id' => $customer2->id]);
    $order3 = Order::factory()->create(['end_customer_id' => $customer1->id]);

    $orders = Order::where('end_customer_id', $customer1->id)->get();

    expect($orders->modelKeys())->toEqualCanonicalizing([$order1->id, $order3->id]);
});

test('nullable end_customer_id allows order creation', function () {
    $customer = Customer::factory()->create();
    $order = Order::factory()->create(['end_customer_id' => $customer->id]);

    expect($order->exists)->toBeTrue();
    expect($order->end_customer_id)->toBe($customer->id);
});

test('order gets sequential order number on creation', function () {
    $order1 = Order::factory()->create();
    $order2 = Order::factory()->create();

    expect($order1->order_number)->not->toBeNull();
    expect($order2->order_number)->not->toBeNull();
    expect($order1->order_number)->not->toBe($order2->order_number);
});

test('order number format is MMG-ORD-YYYY-######', function () {
    $year = now()->year;
    $order = Order::factory()->create();

    expect($order->order_number)->toMatch('/^ORD-'.$year.'-\d{6}$/');
});
