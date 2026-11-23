<?php

use App\Filament\Actions\UpdatePaymentAction;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\PaymentStatus;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
});

/**
 * A Super Admin who may update payments, plus an order worth the given total.
 */
function payableOrder(float $total = 1_000_000): array
{
    $user = User::factory()->create();
    $user->assignRole('Super Admin');
    Permission::firstOrCreate(['name' => 'update_payment_order']);
    $user->givePermissionTo('update_payment_order');

    return [$user, Order::factory()->create(['created_by' => $user->id, 'total_amount' => $total])];
}

it('can render update payment action', function () {
    [$user, $order] = payableOrder();

    actingAs($user);

    $this->get(route('filament.admin.resources.orders.view', $order))
        ->assertSuccessful();
});

it('hides action without permission', function () {
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'update_payment_order']);

    actingAs($user);

    $action = UpdatePaymentAction::make();
    expect($action->isHidden())->toBeTrue();
});

it('persists the effective date the user picked', function () {
    [$user, $order] = payableOrder();

    actingAs($user);

    Livewire::test(ListOrders::class)
        ->mountAction(TestAction::make('updatePayment')->table($order))
        ->setActionData([
            'amount' => 250_000,
            'effective_date' => '2026-11-01',
            'notes' => 'First instalment',
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $payment = $order->paymentStatuses()->sole();

    expect($payment->effective_date->toDateString())->toBe('2026-11-01')
        ->and($payment->notes)->toBe('First instalment')
        ->and($payment->amount)->toBe('250000.00');
});

it('derives a partial status from the running total', function () {
    [$user, $order] = payableOrder(1_000_000);

    actingAs($user);

    Livewire::test(ListOrders::class)
        ->mountAction(TestAction::make('updatePayment')->table($order))
        ->setActionData(['amount' => 400_000, 'effective_date' => now()->toDateString()])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($order->paymentStatuses()->sole()->status)->toBe('partial');
});

it('derives full once the payments cover the order total', function () {
    [$user, $order] = payableOrder(1_000_000);

    actingAs($user);

    foreach ([600_000, 400_000] as $amount) {
        Livewire::test(ListOrders::class)
            ->mountAction(TestAction::make('updatePayment')->table($order))
            ->setActionData(['amount' => $amount, 'effective_date' => now()->toDateString()])
            ->callMountedAction()
            ->assertHasNoActionErrors();
    }

    expect($order->paymentStatuses()->orderBy('id')->pluck('status')->all())
        ->toBe(['partial', 'full'])
        ->and($order->remainingBalance())->toBe(0.0);
});

it('rejects a payment larger than the remaining balance', function () {
    [$user, $order] = payableOrder(1_000_000);

    $order->paymentStatuses()->create([
        'status' => 'partial',
        'amount' => 900_000,
        'effective_date' => now()->toDateString(),
    ]);

    actingAs($user);

    Livewire::test(ListOrders::class)
        ->mountAction(TestAction::make('updatePayment')->table($order))
        ->setActionData(['amount' => 200_000, 'effective_date' => now()->toDateString()])
        ->callMountedAction()
        ->assertHasActionErrors(['amount' => 'max']);

    expect($order->paymentStatuses()->count())->toBe(1);
});

it('offers no submit action once the order is fully paid', function () {
    [$user, $order] = payableOrder(500_000);

    $order->paymentStatuses()->create([
        'status' => 'full',
        'amount' => 500_000,
        'effective_date' => now()->toDateString(),
    ]);

    actingAs($user);

    $action = UpdatePaymentAction::make()->record($order);

    expect($action->getModalSubmitAction())->toBeNull();
});

it('shows the existing payments in the modal above the form', function () {
    [$user, $order] = payableOrder(1_000_000);

    $order->paymentStatuses()->create([
        'status' => 'partial',
        'amount' => 300_000,
        'effective_date' => '2026-10-05',
        'notes' => 'Deposit received',
    ]);

    actingAs($user);

    // The modal's custom content is rendered in a separate Livewire partial, so
    // assert on the content the action hands the modal rather than on the page.
    $content = (string) UpdatePaymentAction::make()->record($order)->getModalContent();

    expect($content)
        ->toContain('Deposit received')
        ->toContain('05 Oct 2026')
        ->toContain('Remaining')
        // Total 1,000,000 against 300,000 paid.
        ->toContain('IDR 700.000');
});

it('sums every payment rather than trusting the newest one', function () {
    [$user, $order] = payableOrder(1_000_000);

    foreach ([100_000, 250_000] as $amount) {
        $order->paymentStatuses()->create([
            'status' => 'partial',
            'amount' => $amount,
            'effective_date' => now()->toDateString(),
        ]);
    }

    expect($order->fresh()->paidAmount())->toBe(350_000.0)
        ->and($order->fresh()->remainingBalance())->toBe(650_000.0);
});

it('ignores a pending payment when summing what was paid', function () {
    [, $order] = payableOrder(1_000_000);

    $order->paymentStatuses()->create([
        'status' => 'pending',
        'amount' => 0,
        'effective_date' => now()->toDateString(),
    ]);

    expect($order->fresh()->paidAmount())->toBe(0.0);
});

it('takes the latest payment by id, not by timestamp', function () {
    [, $order] = payableOrder(1_000_000);

    // Same second on purpose: created_at has no sub-second precision, so an
    // ordering that relies on it alone cannot tell these two apart.
    $stamp = now();

    $first = $order->paymentStatuses()->create([
        'status' => 'partial',
        'amount' => 100_000,
        'effective_date' => $stamp->toDateString(),
    ]);
    $second = $order->paymentStatuses()->create([
        'status' => 'full',
        'amount' => 900_000,
        'effective_date' => $stamp->toDateString(),
    ]);

    $first->forceFill(['created_at' => $stamp])->saveQuietly();
    $second->forceFill(['created_at' => $stamp])->saveQuietly();

    expect($order->fresh()->latestPaymentStatus->id)->toBe($second->id);
});

it('re-derives later statuses when an earlier payment is deleted', function () {
    [, $order] = payableOrder(1_000_000);

    $first = $order->paymentStatuses()->create([
        'status' => 'partial',
        'amount' => 500_000,
        'effective_date' => now()->toDateString(),
    ]);
    $second = $order->paymentStatuses()->create([
        'status' => 'full',
        'amount' => 500_000,
        'effective_date' => now()->toDateString(),
    ]);

    $first->delete();
    PaymentStatus::resyncStatuses($order->fresh());

    expect($second->fresh()->status)->toBe('partial')
        ->and($order->fresh()->remainingBalance())->toBe(500_000.0);
});
