<?php

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Widgets\SalesOrdersWidget;
use App\Models\DeliveryStatus;
use App\Models\Department;
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
    $sales = Department::factory()->create(['name' => Department::SALES]);
    $user = User::factory()->create(['department_id' => $sales->id]);
    $user->assignRole('Super Admin');
    $otherUser = User::factory()->create();

    $ownShipped = Order::factory()->create(['created_by' => $user->id, 'order_date' => now()]);
    $otherShipped = Order::factory()->create(['created_by' => $otherUser->id, 'order_date' => now()]);

    DeliveryStatus::create(['order_id' => $ownShipped->id, 'shipped_date' => now()]);
    DeliveryStatus::create(['order_id' => $otherShipped->id, 'shipped_date' => now()]);

    // Sales and Marketing keep the personal shipped count; staff outside those
    // departments see the whole company, covered by the next test.
    Livewire::actingAs($user)
        ->test(SalesOrdersWidget::class)
        ->assertSee('1 orders');
});

test('staff outside Sales and Marketing see company-wide order stats', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Admin');
    $otherUser = User::factory()->create();

    $ownShipped = Order::factory()->create(['created_by' => $user->id, 'order_date' => now()]);
    $otherShipped = Order::factory()->create(['created_by' => $otherUser->id, 'order_date' => now()]);

    DeliveryStatus::create(['order_id' => $ownShipped->id, 'shipped_date' => now()]);
    DeliveryStatus::create(['order_id' => $otherShipped->id, 'shipped_date' => now()]);

    Livewire::actingAs($user)
        ->test(SalesOrdersWidget::class)
        ->assertSee('2 orders');
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
