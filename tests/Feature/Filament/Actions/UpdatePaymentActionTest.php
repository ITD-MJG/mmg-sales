<?php

use App\Filament\Actions\UpdatePaymentAction;
use App\Models\Order;
use App\Models\User;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

it('can render update payment action', function () {
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'update_payment_order']);
    Permission::firstOrCreate(['name' => 'view_order']);
    Permission::firstOrCreate(['name' => 'view_any_order']);
    $user->givePermissionTo(['update_payment_order', 'view_order', 'view_any_order']);
    $order = Order::factory()->create(['created_by' => $user->id]);

    actingAs($user);

    $this->get(route('filament.admin.resources.orders.view', $order))
        ->assertSuccessful();
});

it('creates payment status record', function () {
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'update_payment_order']);
    $user->givePermissionTo('update_payment_order');
    $order = Order::factory()->create(['created_by' => $user->id]);

    actingAs($user);

    $this->assertDatabaseCount('payment_statuses', 0);

    $order->paymentStatuses()->create([
        'status' => 'partial',
        'amount' => 500000,
        'notes' => 'Partial payment',
    ]);

    $this->assertDatabaseCount('payment_statuses', 1);
    $this->assertDatabaseHas('payment_statuses', [
        'order_id' => $order->id,
        'status' => 'partial',
        'amount' => 500000,
    ]);
});

it('hides action without permission', function () {
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'update_payment_order']);

    actingAs($user);

    $action = UpdatePaymentAction::make();
    expect($action->isHidden())->toBeTrue();
});
