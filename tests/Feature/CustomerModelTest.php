<?php

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;

test('customer_name is nullable and accepts string values', function () {
    $customer = Customer::factory()->create(['customer_name' => 'Test Customer Name']);

    expect($customer->customer_name)->toBe('Test Customer Name');
});

test('customer model accepts customer_name in mass assignment', function () {
    $customer = Customer::create([
        'name' => 'Test Customer',
        'customer_name' => 'Display Name',
    ]);

    expect($customer->customer_name)->toBe('Display Name');
});

test('displayName accessor returns customer_name when set', function () {
    $customer = Customer::factory()->create([
        'name' => 'Internal Name',
        'customer_name' => 'Display Name',
    ]);

    expect($customer->displayName)->toBe('Display Name');
});

test('displayName accessor falls back to name when customer_name is null', function () {
    $customer = Customer::factory()->create([
        'name' => 'Fallback Name',
        'customer_name' => null,
    ]);

    expect($customer->displayName)->toBe('Fallback Name');
});

it('relates both raw leads and opportunities', function () {
    $customer = Customer::factory()->create();

    Lead::factory()->create(['customer_id' => $customer->id]);
    Opportunity::factory()->create(['customer_id' => $customer->id]);

    expect($customer->leads()->count())->toBe(1)
        ->and($customer->opportunities()->count())->toBe(1);
});
