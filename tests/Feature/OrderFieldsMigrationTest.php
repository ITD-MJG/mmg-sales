<?php

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('orders allow a null department and one optional sales user', function () {
    expect(Schema::hasColumn('orders', 'sales_id'))->toBeTrue()
        ->and(Schema::hasColumn('orders', 'sales'))->toBeFalse()
        ->and(Schema::hasColumn('orders', 'head_position_id'))->toBeFalse()
        ->and(Schema::hasColumn('orders', 'pm_jpm_pe_position_id'))->toBeFalse()
        ->and(Schema::hasColumn('orders', 'rsm_asm_position_id'))->toBeFalse()
        ->and(Schema::hasColumn('orders', 'spv_position_id'))->toBeFalse();

    $order = Order::factory()->create([
        'department_id' => null,
        'sales_id' => null,
    ]);

    expect($order->fresh()->department_id)->toBeNull()
        ->and($order->sales_id)->toBeNull();
});
