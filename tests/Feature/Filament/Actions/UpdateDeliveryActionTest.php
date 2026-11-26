<?php

use App\Filament\Actions\UpdateDeliveryAction;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

it('can render update delivery action', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Super Admin');
    $order = Order::factory()->create(['created_by' => $user->id]);

    actingAs($user);

    $this->get(route('filament.admin.resources.orders.view', $order))
        ->assertSuccessful()
        ->assertSee('Update Delivery')
        ->assertSee('Update Payment');
});

it('creates delivery status record', function () {
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'update_delivery_order']);
    $user->givePermissionTo('update_delivery_order');
    $order = Order::factory()->create(['created_by' => $user->id]);

    actingAs($user);

    $this->assertDatabaseCount('delivery_statuses', 0);

    UpdateDeliveryAction::make()->record($order)->call([
        'data' => [
            'carrier' => 'JNE',
            'tracking_number' => 'TEST123',
            'shipped_date' => now()->toDateString(),
        ],
    ]);

    $this->assertDatabaseCount('delivery_statuses', 1);
    $this->assertDatabaseHas('delivery_statuses', [
        'order_id' => $order->id,
        'carrier' => 'JNE',
        'tracking_number' => 'TEST123',
    ]);
});

it('refuses to create a delivery without permission', function () {
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'update_delivery_order']);

    actingAs($user);

    $order = Order::factory()->create(['created_by' => $user->id]);

    UpdateDeliveryAction::make()->record($order)->call([
        'data' => ['carrier' => 'JNE'],
    ]);
})->throws(HttpException::class);

it('rejects a proof of delivery that is not a URL', function () {
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'update_delivery_order']);
    $user->givePermissionTo(['view_any_order', 'view_order', 'update_delivery_order']);
    $order = Order::factory()->create(['created_by' => $user->id]);

    actingAs($user);

    livewire(ListOrders::class)
        ->mountAction(TestAction::make('updateDelivery')->table($order))
        ->fillForm(['proof_of_delivery' => 'javascript:alert(1)'])
        ->callMountedAction()
        ->assertHasFormErrors(['proof_of_delivery']);

    expect($order->deliveryStatuses()->count())->toBe(0);
});

it('shows recorded deliveries in the history modal', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Super Admin');
    $order = Order::factory()->create(['created_by' => $user->id]);

    $order->deliveryStatuses()->create([
        'carrier' => 'JNE',
        'tracking_number' => 'TRK-HISTORY',
        'shipped_date' => now()->toDateString(),
    ]);

    actingAs($user);

    $html = (string) UpdateDeliveryAction::make()->record($order)->getModalContent();

    expect($html)->toContain('JNE')
        ->toContain('TRK-HISTORY')
        ->toContain('Shipped');
});

it('hides action without permission', function () {
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'update_delivery_order']);

    actingAs($user);

    $action = UpdateDeliveryAction::make();
    expect($action->isHidden())->toBeTrue();
});
