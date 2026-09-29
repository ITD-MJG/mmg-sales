<?php

use App\Models\Opportunity;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

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
    $unrelated = Opportunity::factory()->stage('negotiation')->create();

    $order = Order::factory()->create(['opportunity_id' => null]);

    expect($order->exists)->toBeTrue()
        ->and($order->opportunity_id)->toBeNull()
        ->and($unrelated->refresh()->stage)->toBe('negotiation')
        ->and($unrelated->converted_at)->toBeNull();
});

it('returns cleanly when the linked opportunity row is missing', function () {
    // Bypass the FK so the order row can hold an id that resolves to nothing.
    $order = Schema::withoutForeignKeyConstraints(
        fn () => Order::factory()->create(['opportunity_id' => 999999])
    );

    expect($order->exists)->toBeTrue()
        ->and((int) $order->opportunity_id)->toBe(999999)
        ->and($order->opportunity)->toBeNull();
});

it('relates the opportunity', function () {
    $opportunity = Opportunity::factory()->create();
    $order = Order::factory()->create(['opportunity_id' => $opportunity->id]);

    expect($order->opportunity->id)->toBe($opportunity->id);
});
