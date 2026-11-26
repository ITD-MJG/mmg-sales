<?php

namespace Tests\Feature\Filament\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\DeliveryStatus;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\seed;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['view_any_order', 'view_order', 'update_delivery_order']);

    $this->actingAs($this->user);
});

/**
 * @return array{0: Order, 1: DeliveryStatus}
 */
function orderWithDelivery(): array
{
    $order = Order::factory()->create();

    $delivery = $order->deliveryStatuses()->create([
        'carrier' => 'JNE',
        'tracking_number' => 'TRK-001',
        'shipped_date' => '2026-11-01',
        'notes' => 'First shipment',
    ]);

    return [$order, $delivery];
}

it('renders an edit and a delete button for every delivery in the update modal', function (): void {
    [$order, $delivery] = orderWithDelivery();

    $component = livewire(ListOrders::class)
        ->mountAction(TestAction::make('updateDelivery')->table($order));

    $history = (string) $component->instance()->getMountedAction()->getModalContent()->render();

    expect($history)
        ->toContain("mountAction('editDelivery', { delivery: {$delivery->getKey()} })")
        ->toContain("mountAction('deleteDelivery', { delivery: {$delivery->getKey()} })");
});

it('edits an existing delivery', function (): void {
    [$order, $delivery] = orderWithDelivery();

    livewire(ListOrders::class)
        ->mountAction(TestAction::make('updateDelivery')->table($order))
        ->mountAction('editDelivery', ['delivery' => $delivery->getKey()])
        ->assertSchemaStateSet([
            'carrier' => 'JNE',
            'tracking_number' => 'TRK-001',
            'shipped_date' => '2026-11-01',
            'notes' => 'First shipment',
        ])
        ->fillForm([
            'carrier' => 'J&T',
            'tracking_number' => 'TRK-002',
            'delivered_date' => '2026-11-03',
            'notes' => 'Corrected shipment',
        ])
        ->callMountedAction();

    $delivery->refresh();

    expect($delivery->carrier)->toBe('J&T')
        ->and($delivery->tracking_number)->toBe('TRK-002')
        ->and($delivery->delivered_date->toDateString())->toBe('2026-11-03')
        ->and($delivery->notes)->toBe('Corrected shipment');
});

it('rejects a delivered date before the shipped date', function (): void {
    [$order, $delivery] = orderWithDelivery();

    livewire(ListOrders::class)
        ->mountAction(TestAction::make('updateDelivery')->table($order))
        ->mountAction('editDelivery', ['delivery' => $delivery->getKey()])
        ->fillForm(['delivered_date' => '2026-10-01'])
        ->callMountedAction()
        ->assertHasFormErrors(['delivered_date']);

    expect($delivery->fresh()->delivered_date)->toBeNull();
});

it('refuses a delivery id that belongs to another order', function (): void {
    [$order] = orderWithDelivery();
    [$otherOrder, $foreignDelivery] = orderWithDelivery();

    livewire(ListOrders::class)
        ->mountAction(TestAction::make('updateDelivery')->table($order))
        ->mountAction('editDelivery', ['delivery' => $foreignDelivery->getKey()])
        ->fillForm(['carrier' => 'Hijacked'])
        ->callMountedAction();

    expect($foreignDelivery->fresh()->carrier)->toBe('JNE');
});

it('deletes an existing delivery', function (): void {
    [$order, $delivery] = orderWithDelivery();

    livewire(ListOrders::class)
        ->mountAction(TestAction::make('updateDelivery')->table($order))
        ->mountAction('deleteDelivery', ['delivery' => $delivery->getKey()])
        ->callMountedAction();

    expect($order->deliveryStatuses()->whereKey($delivery->getKey())->exists())->toBeFalse();
});

it('refuses to delete a delivery id that belongs to another order', function (): void {
    [$order] = orderWithDelivery();
    [$otherOrder, $foreignDelivery] = orderWithDelivery();

    livewire(ListOrders::class)
        ->mountAction(TestAction::make('updateDelivery')->table($order))
        ->mountAction('deleteDelivery', ['delivery' => $foreignDelivery->getKey()])
        ->callMountedAction();

    expect($otherOrder->deliveryStatuses()->whereKey($foreignDelivery->getKey())->exists())->toBeTrue();
});
