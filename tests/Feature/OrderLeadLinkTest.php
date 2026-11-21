<?php

use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('has the lead_id column', function () {
    expect(Schema::hasColumn('orders', 'lead_id'))->toBeTrue();
});

it('accepts an order with no lead and no opportunity', function () {
    $order = Order::factory()->create(['lead_id' => null, 'opportunity_id' => null]);

    expect($order->exists)->toBeTrue();
});

it('accepts an order on a lead only', function () {
    $lead = Lead::factory()->create();
    $order = Order::factory()->create(['lead_id' => $lead->id, 'opportunity_id' => null]);

    expect($order->lead->id)->toBe($lead->id);
});

it('accepts an order on an opportunity only', function () {
    $opportunity = Opportunity::factory()->create();
    $order = Order::factory()->create(['lead_id' => null, 'opportunity_id' => $opportunity->id]);

    expect($order->opportunity->id)->toBe($opportunity->id);
});

it('rejects an order on both a lead and an opportunity', function () {
    $lead = Lead::factory()->create();
    $opportunity = Opportunity::factory()->create();

    expect(fn () => Order::factory()->create([
        'lead_id' => $lead->id,
        'opportunity_id' => $opportunity->id,
    ]))->toThrow(InvalidArgumentException::class, 'Order cannot belong to both a lead and an opportunity.');
});

it('nulls lead_id rather than deleting the order when the lead is removed', function () {
    $lead = Lead::factory()->create();
    $order = Order::factory()->create(['lead_id' => $lead->id, 'opportunity_id' => null]);

    $lead->forceDelete();

    expect(Order::find($order->id))->not->toBeNull()
        ->and(Order::find($order->id)->lead_id)->toBeNull();
});

it('reaches the opportunity through the lead lineage without re-pointing the order', function () {
    $lead = Lead::factory()->create();
    $order = Order::factory()->create(['lead_id' => $lead->id, 'opportunity_id' => null]);

    $opportunity = Opportunity::factory()->create(['converted_from_lead_id' => $lead->id]);

    // The order keeps its origin pointer and still resolves the deal.
    expect($order->fresh()->lead_id)->toBe($lead->id)
        ->and($order->fresh()->lead->opportunity->id)->toBe($opportunity->id);
});
