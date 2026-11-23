<?php

namespace App\Filament\Actions;

use App\Models\Order;
use App\Models\PaymentStatus;
use Filament\Actions\Action;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;

/**
 * Removes a payment that was recorded by mistake.
 *
 * Registered as a modal action of UpdatePaymentAction and mounted from the
 * per-row Delete buttons in its payment history; it has no button of its own.
 * The row arrives as the `payment` argument, which an action can read directly
 * (unlike a form field, see EditPaymentAction).
 */
class DeletePaymentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'deletePayment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Delete')
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->modalHeading('Delete payment')
            ->modalSubmitActionLabel('Delete')
            ->requiresConfirmation()
            ->modalDescription(fn (Order $record, array $arguments): string => self::describe($record, $arguments))
            // Confirmation-only, so mountUsing carries the guard: neither a
            // foreign payment id nor a user without the permission gets as far
            // as seeing the confirm button.
            ->mountUsing(function (Order $record, array $arguments): void {
                if (! self::payment($record, $arguments['payment'] ?? null)
                    || ! (auth()->user()?->can('update_payment_order') ?? false)) {
                    throw new Halt;
                }
            })
            ->action(function (Order $record, array $arguments): void {
                $payment = self::payment($record, $arguments['payment'] ?? null);

                if (! $payment) {
                    return;
                }

                $payment->delete();

                // Every later row's status is a snapshot of the running total,
                // so removing one amount invalidates the whole chain.
                PaymentStatus::resyncStatuses($record);
            })
            ->successNotificationTitle('Payment deleted');
    }

    /**
     * The payment this action was mounted for. Scoped through the order so an
     * id belonging to a different order cannot be deleted through it.
     */
    private static function payment(Order $order, mixed $id): ?PaymentStatus
    {
        if (! filled($id)) {
            return null;
        }

        return $order->paymentStatuses()->whereKey($id)->first();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private static function describe(Order $order, array $arguments): string
    {
        $payment = self::payment($order, $arguments['payment'] ?? null);

        if (! $payment) {
            return 'This payment can no longer be deleted.';
        }

        return sprintf(
            'This removes the IDR %s payment recorded for %s. The remaining payment statuses are recalculated.',
            number_format((float) $payment->amount, 0, ',', '.'),
            $payment->effective_date?->format('d M Y') ?? 'no date',
        );
    }
}
