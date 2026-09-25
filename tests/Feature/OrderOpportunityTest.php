<?php

use App\Models\Opportunity;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('saves an order with a null opportunity_id', function () {
    $order = Order::factory()->create(['opportunity_id' => null]);

    expect($order->exists)->toBeTrue()
        ->and($order->opportunity_id)->toBeNull();
});

it('marks a linked opportunity as won when an order is created', function () {
    $opportunity = Opportunity::factory()->stage('negotiation')->create();

    Order::factory()->create(['opportunity_id' => $opportunity->id]);

    $opportunity->refresh();

    expect($opportunity->stage)->toBe('won')
        ->and($opportunity->converted_at)->not->toBeNull();
});

it('does not fail when an order has no opportunity', function () {
    expect(fn () => Order::factory()->create(['opportunity_id' => null]))
        ->not->toThrow(Throwable::class);
});

it('relates the opportunity', function () {
    $opportunity = Opportunity::factory()->create();
    $order = Order::factory()->create(['opportunity_id' => $opportunity->id]);

    expect($order->opportunity->id)->toBe($opportunity->id);
});
