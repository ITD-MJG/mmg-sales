<?php

namespace Tests\Feature\Filament\Orders;

use App\Filament\Actions\UpdatePaymentAction;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\seed;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['view_any_order', 'view_order', 'update_payment_order']);

    $this->actingAs($this->user);
});

function orderForView(?float $totalAmount = 1000000): Order
{
    $order = Order::factory()->create(['total_amount' => $totalAmount]);

    $order->paymentStatuses()->create([
        'amount' => 250000,
        'effective_date' => '2026-11-01',
        'notes' => 'First instalment',
        'status' => 'partial',
    ]);

    return $order;
}

it('offers the update payment action in the order view header', function (): void {
    $order = orderForView();

    livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertActionExists(UpdatePaymentAction::class);
});

it('records a payment from the order view header', function (): void {
    $order = orderForView();

    livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction(UpdatePaymentAction::class, [
            'amount' => 750000,
            'effective_date' => '2026-11-20',
            'notes' => 'Settled',
        ]);

    $payments = $order->paymentStatuses()->orderBy('id')->get();

    expect($payments)->toHaveCount(2)
        ->and((float) $payments->last()->amount)->toBe(750000.0)
        ->and($payments->last()->status)->toBe('full')
        ->and((float) $order->fresh()->paidAmount())->toBe(1000000.0);
});

it('shows the existing payments in the view page modal', function (): void {
    $order = orderForView();

    $component = livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->mountAction(UpdatePaymentAction::class);

    $history = (string) $component->instance()->getMountedAction()->getModalContent()->render();

    expect($history)
        ->toContain('First instalment')
        ->toContain("mountAction('editPayment', { payment: {$order->paymentStatuses()->sole()->getKey()} })")
        ->toContain("mountAction('deletePayment', { payment: {$order->paymentStatuses()->sole()->getKey()} })");
});

it('edits a payment from the view page modal', function (): void {
    $order = orderForView();
    $payment = $order->paymentStatuses()->sole();

    livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->mountAction(UpdatePaymentAction::class)
        ->mountAction('editPayment', ['payment' => $payment->getKey()])
        ->fillForm(['amount' => 400000])
        ->callMountedAction();

    expect((float) $payment->fresh()->amount)->toBe(400000.0)
        ->and((float) $order->fresh()->paidAmount())->toBe(400000.0);
});

it('deletes a payment from the view page modal', function (): void {
    $order = orderForView();
    $payment = $order->paymentStatuses()->sole();

    livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->mountAction(UpdatePaymentAction::class)
        ->mountAction('deletePayment', ['payment' => $payment->getKey()])
        ->callMountedAction();

    expect($order->paymentStatuses()->exists())->toBeFalse()
        ->and((float) $order->fresh()->paidAmount())->toBe(0.0);
});

it('hides the update payment action without the permission', function (): void {
    $order = orderForView();

    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['view_any_order', 'view_order']);
    $this->actingAs($viewer);

    livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertActionHidden(UpdatePaymentAction::class);
});
