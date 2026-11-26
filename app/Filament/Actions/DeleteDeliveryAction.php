<?php

namespace App\Filament\Actions;

use App\Models\DeliveryStatus;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;

/**
 * Removes a delivery update that was recorded by mistake.
 *
 * Registered as a modal action of UpdateDeliveryAction and mounted from the
 * per-row Delete buttons in its delivery history; it has no button of its own.
 * The row arrives as the `delivery` argument, which an action can read directly
 * (unlike a form field, see EditDeliveryAction).
 */
class DeleteDeliveryAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'deleteDelivery';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Delete')
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->modalHeading('Delete delivery')
            ->modalSubmitActionLabel('Delete')
            ->requiresConfirmation()
            ->modalDescription(fn (Order $record, array $arguments): string => self::describe($record, $arguments))
            // Confirmation-only, so mountUsing carries the guard: neither a
            // foreign delivery id nor a user without the permission gets as far
            // as seeing the confirm button.
            ->mountUsing(function (Order $record, array $arguments): void {
                if (! self::delivery($record, $arguments['delivery'] ?? null)
                    || ! (auth()->user()?->can('update_delivery_order') ?? false)) {
                    throw new Halt;
                }
            })
            ->action(function (Order $record, array $arguments): void {
                $delivery = self::delivery($record, $arguments['delivery'] ?? null);

                if (! $delivery) {
                    return;
                }

                $delivery->delete();
            })
            ->successNotificationTitle('Delivery deleted');
    }

    /**
     * The delivery this action was mounted for. Scoped through the order so an
     * id belonging to a different order cannot be deleted through it.
     */
    private static function delivery(Order $order, mixed $id): ?DeliveryStatus
    {
        if (! filled($id)) {
            return null;
        }

        return $order->deliveryStatuses()->whereKey($id)->first();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private static function describe(Order $order, array $arguments): string
    {
        $delivery = self::delivery($order, $arguments['delivery'] ?? null);

        if (! $delivery) {
            return 'This delivery can no longer be deleted.';
        }

        return sprintf(
            'This removes the %s delivery recorded with tracking %s.',
            $delivery->carrier ?: 'carrier-less',
            $delivery->tracking_number ?: 'none',
        );
    }
}
