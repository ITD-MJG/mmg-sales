<?php

use App\Filament\Actions\UpdateDeliveryAction;
use App\Models\DeliveryStatus;
use App\Models\Order;
use App\Models\User;
use Spatie\Permission\Models\Permission;

it('has correct default name', function (): void
{
    $action = UpdateDeliveryAction::make('updateDelivery');

    expect($action->getName())->toBe('updateDelivery');
});

it('is visible when user has permission', function (): void
{
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'update_delivery_order']);
    $user->givePermissionTo('update_delivery_order');

    $action = UpdateDeliveryAction::make('updateDelivery');

    $this->actingAs($user);

    expect($action->isVisible())->toBeTrue();
});

it('is not visible when user lacks permission', function (): void
{
    $user = User::factory()->create();

    $action = UpdateDeliveryAction::make('updateDelivery');

    $this->actingAs($user);

    expect($action->isVisible())->toBeFalse();
});

it('creates a delivery status record when the action runs', function (): void {
    $order = Order::factory()->create();
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'update_delivery_order']);
    $user->givePermissionTo('update_delivery_order');

    $this->actingAs($user);

    $data = [
        'carrier' => 'JNE',
        'tracking_number' => 'TRK001',
        'shipped_date' => now()->toDateString(),
        'delivered_date' => null,
        'proof_of_delivery' => null,
        'notes' => 'Test shipment',
    ];

    // Call the action's own handler, not the form schema: evaluate() would
    // invoke the form definition and never reach the closure that persists.
    $action = UpdateDeliveryAction::make('updateDelivery');
    $action->record($order);
    $action->call(['data' => $data]);

    $this->assertDatabaseHas(DeliveryStatus::class, [
        'order_id' => $order->id,
        'carrier' => 'JNE',
        'tracking_number' => 'TRK001',
        'notes' => 'Test shipment',
    ]);
});
