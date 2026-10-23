<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('orders table shows the stored net sales amount', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create([
        'created_by' => $user->id,
        'net_sales_total' => null,
        'total_amount' => 98765,
    ]);

    Livewire::actingAs($user)
        ->test(ListOrders::class)
        ->assertTableColumnStateSet('total_amount', 98765, $order);
});
