<?php

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('takes the newest delivery row by id, not by timestamp', function () {
    $order = Order::factory()->create();

    $olderInsertNewerTimestamp = $order->deliveryStatuses()->create([
        'carrier' => 'First inserted',
    ]);
    $olderInsertNewerTimestamp->forceFill(['created_at' => '2026-11-05 10:00:00'])->save();

    $newerInsertOlderTimestamp = $order->deliveryStatuses()->create([
        'carrier' => 'Second inserted',
    ]);
    $newerInsertOlderTimestamp->forceFill(['created_at' => '2026-11-01 10:00:00'])->save();

    $order->unsetRelation('deliveryStatuses');

    expect($order->latestDeliveryStatus->is($newerInsertOlderTimestamp))->toBeTrue()
        ->and($order->latestDeliveryStatus->is($olderInsertNewerTimestamp))->toBeFalse();
});
