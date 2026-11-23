<?php

namespace Tests\Feature\Filament\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\PaymentStatus;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\seed;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seed(RolesAndPermissionsSeeder::class);

    // No Gate::before bypass: the point of the last test is that the edit
    // screen is closed to a user without update_payment_order.
    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['view_any_order', 'view_order', 'update_payment_order']);

    $this->actingAs($this->user);
});

/**
 * A 1,000,000 order carrying two 250,000 payments, so the second one is the
 * newest and the chain has something to re-derive.
 *
 * @return array{0: Order, 1: PaymentStatus, 2: PaymentStatus}
 */
function orderWithTwoPayments(): array
{
    $order = Order::factory()->create(['total_amount' => 1000000]);

    $first = $order->paymentStatuses()->create([
        'amount' => 250000,
        'effective_date' => '2026-11-01',
        'notes' => 'First instalment',
        'status' => 'partial',
    ]);

    $second = $order->paymentStatuses()->create([
        'amount' => 250000,
        'effective_date' => '2026-11-10',
        'notes' => 'Second instalment',
        'status' => 'partial',
    ]);

    return [$order, $first, $second];
}

it('renders an edit button for every payment in the update payment modal', function (): void {
    [$order, $first, $second] = orderWithTwoPayments();

    $component = livewire(ListOrders::class)
        ->mountAction(TestAction::make('updatePayment')->table($order));

    // Action modals render through a Livewire partial that html() leaves out,
    // so the modal content is rendered directly.
    $history = (string) $component->instance()->getMountedAction()->getModalContent()->render();

    expect($history)
        ->toContain("mountAction('editPayment', { payment: {$first->getKey()} })")
        ->toContain("mountAction('editPayment', { payment: {$second->getKey()} })");
});

it('edits an existing payment and re-derives the status chain', function (): void {
    [$order, $first] = orderWithTwoPayments();

    livewire(ListOrders::class)
        ->mountAction(TestAction::make('updatePayment')->table($order))
        ->mountAction('editPayment', ['payment' => $first->getKey()])
        ->assertSchemaStateSet([
            'amount' => 250000.0,
            'effective_date' => '2026-11-01',
            'notes' => 'First instalment',
        ])
        ->fillForm([
            'amount' => 750000,
            'notes' => 'Corrected first instalment',
        ])
        ->callMountedAction();

    $first->refresh();

    expect((float) $first->amount)->toBe(750000.0)
        ->and($first->notes)->toBe('Corrected first instalment')
        ->and((float) $order->fresh()->paidAmount())->toBe(1000000.0);

    // 750,000 then 1,000,000 of a 1,000,000 total: the running total only
    // reaches the total on the second row, so the first stays partial.
    expect($first->status)->toBe('partial')
        ->and($order->paymentStatuses()->orderBy('id')->get()->last()->status)->toBe('full');
});

it('re-derives later rows when an earlier payment shrinks', function (): void {
    [$order, $first] = orderWithTwoPayments();

    livewire(ListOrders::class)
        ->mountAction(TestAction::make('updatePayment')->table($order))
        ->mountAction('editPayment', ['payment' => $first->getKey()])
        ->fillForm(['amount' => 100000])
        ->callMountedAction();

    // 100,000 + 250,000 is still only 350,000 of 1,000,000.
    expect($first->fresh()->status)->toBe('partial')
        ->and((float) $order->fresh()->paidAmount())->toBe(350000.0);
});

it('refuses a payment id that belongs to another order', function (): void {
    [$order] = orderWithTwoPayments();
    [$otherOrder, $foreignPayment] = orderWithTwoPayments();

    livewire(ListOrders::class)
        ->mountAction(TestAction::make('updatePayment')->table($order))
        ->mountAction('editPayment', ['payment' => $foreignPayment->getKey()])
        ->callMountedAction();

    expect((float) $foreignPayment->fresh()->amount)->toBe(250000.0);
});

it('caps the amount at what the order can still take', function (): void {
    [$order, $first] = orderWithTwoPayments();

    livewire(ListOrders::class)
        ->mountAction(TestAction::make('updatePayment')->table($order))
        ->mountAction('editPayment', ['payment' => $first->getKey()])
        ->fillForm(['amount' => 2000000])
        ->callMountedAction()
        ->assertHasFormErrors(['amount' => 'max']);

    expect((float) $first->fresh()->amount)->toBe(250000.0);
});

it('hides the edit screen from users without update_payment_order', function (): void {
    [$order, $first] = orderWithTwoPayments();

    // Can list orders, but not record payments.
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['view_any_order', 'view_order']);
    $this->actingAs($viewer);

    livewire(ListOrders::class)
        ->mountAction(TestAction::make('updatePayment')->table($order))
        ->mountAction('editPayment', ['payment' => $first->getKey()])
        ->fillForm(['amount' => 900000])
        ->callMountedAction();

    expect((float) $first->fresh()->amount)->toBe(250000.0);
});
