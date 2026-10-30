<?php

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('orders store an optional PO number before subtotal', function () {
    expect(Schema::hasColumn('orders', 'order_source'))->toBeTrue()
        ->and(Schema::hasColumn('orders', 'po_number'))->toBeTrue();

    $order = Order::factory()->create(['order_source' => 'manual', 'po_number' => 'PO-123']);

    expect($order->fresh()->order_source)->toBe('manual')
        ->and($order->fresh()->po_number)->toBe('PO-123');
});

test('orders have no department column and allow one optional sales user', function () {
    expect(Schema::hasColumn('orders', 'sales_id'))->toBeTrue()
        ->and(Schema::hasColumn('orders', 'sales'))->toBeFalse()
        ->and(Schema::hasColumn('orders', 'head_position_id'))->toBeFalse()
        ->and(Schema::hasColumn('orders', 'pm_jpm_pe_position_id'))->toBeFalse()
        ->and(Schema::hasColumn('orders', 'rsm_asm_position_id'))->toBeFalse()
        ->and(Schema::hasColumn('orders', 'spv_position_id'))->toBeFalse()
        ->and(Schema::hasColumn('orders', 'department_id'))->toBeFalse();

    $order = Order::factory()->create(['sales_id' => null]);

    expect($order->sales_id)->toBeNull();
});
