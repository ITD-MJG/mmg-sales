<?php

use App\Filament\Actions\UpdateDeliveryAction;
use App\Models\Order;
use App\Models\User;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

it('can render update delivery action', function () {
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'update_delivery_order']);
    Permission::firstOrCreate(['name' => 'view_order']);
    Permission::firstOrCreate(['name' => 'view_any_order']);
    $user->givePermissionTo(['update_delivery_order', 'view_order', 'view_any_order']);
    $order = Order::factory()->create(['created_by' => $user->id]);

    actingAs($user);

    $this->get(route('filament.admin.resources.orders.view', $order))
        ->assertSuccessful();
});

it('creates delivery status record', function () {
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'update_delivery_order']);
    $user->givePermissionTo('update_delivery_order');
    $order = Order::factory()->create(['created_by' => $user->id]);

    actingAs($user);

    $this->assertDatabaseCount('delivery_statuses', 0);

    $order->deliveryStatuses()->create([
        'carrier' => 'JNE',
        'tracking_number' => 'TEST123',
        'shipped_date' => now()->toDateString(),
    ]);

    $this->assertDatabaseCount('delivery_statuses', 1);
    $this->assertDatabaseHas('delivery_statuses', [
        'order_id' => $order->id,
        'carrier' => 'JNE',
        'tracking_number' => 'TEST123',
    ]);
});

it('hides action without permission', function () {
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'update_delivery_order']);

    actingAs($user);

    $action = UpdateDeliveryAction::make();
    expect($action->isHidden())->toBeTrue();
});
