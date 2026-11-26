<?php

use App\Models\DeliveryStatus;

it('derives pending when nothing is recorded', function () {
    $delivery = new DeliveryStatus;

    expect($delivery->status)->toBe('pending')
        ->and($delivery->status_label)->toBe('Pending');
});

it('derives in transit from a carrier', function () {
    $delivery = new DeliveryStatus(['carrier' => 'JNE']);

    expect($delivery->status)->toBe('in_transit')
        ->and($delivery->status_label)->toBe('In Transit');
});

it('derives shipped from a shipped date', function () {
    $delivery = new DeliveryStatus(['carrier' => 'JNE', 'shipped_date' => '2026-11-01']);

    expect($delivery->status)->toBe('shipped')
        ->and($delivery->status_label)->toBe('Shipped');
});

it('derives delivered from a delivered date', function () {
    $delivery = new DeliveryStatus([
        'carrier' => 'JNE',
        'shipped_date' => '2026-11-01',
        'delivered_date' => '2026-11-03',
    ]);

    expect($delivery->status)->toBe('delivered')
        ->and($delivery->status_label)->toBe('Delivered');
});
