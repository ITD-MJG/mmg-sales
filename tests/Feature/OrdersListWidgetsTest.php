<?php

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Widgets\SalesOrdersWidget;
use App\Models\DeliveryStatus;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('shipped order stats only count the authenticated users orders', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Admin');
    $otherUser = User::factory()->create();

    $ownShipped = Order::factory()->create(['created_by' => $user->id, 'order_date' => now()]);
    $otherShipped = Order::factory()->create(['created_by' => $otherUser->id, 'order_date' => now()]);

    DeliveryStatus::create(['order_id' => $ownShipped->id, 'shipped_date' => now()]);
    DeliveryStatus::create(['order_id' => $otherShipped->id, 'shipped_date' => now()]);

    // Shipped is the only stat scoped to the authenticated user; open and total
    // counts are deliberately global.
    Livewire::actingAs($user)
        ->test(SalesOrdersWidget::class)
        ->assertSee('1 orders');
});

test('the orders table shows the net sales total', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Admin');
    $order = Order::factory()->create([
        'created_by' => $user->id,
        'total_amount' => 250,
    ]);

    Livewire::actingAs($user)
        ->test(ListOrders::class)
        ->assertTableColumnStateSet('total_amount', $order->total_amount, $order);
});
