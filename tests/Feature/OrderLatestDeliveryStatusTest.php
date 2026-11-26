<?php

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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

it('loads the latest delivery for a whole page in one query', function () {
    $orders = Order::factory()->count(5)->create();

    foreach ($orders as $order) {
        $order->deliveryStatuses()->create(['carrier' => 'Old']);
        $order->deliveryStatuses()->create(['carrier' => 'New']);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $loaded = Order::with('latestDeliveryStatus')->get();
    $carriers = $loaded->map(fn (Order $order): ?string => $order->latestDeliveryStatus?->carrier)->all();

    $deliveryQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'delivery_statuses'))
        ->count();

    DB::disableQueryLog();

    expect($carriers)->toBe(array_fill(0, 5, 'New'))
        ->and($deliveryQueries)->toBe(1);
});
