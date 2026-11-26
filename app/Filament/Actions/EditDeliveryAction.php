<?php

namespace App\Filament\Actions;

use App\Models\DeliveryStatus;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;

/**
 * Corrects a delivery update that is already recorded.
 *
 * Registered as a modal action of UpdateDeliveryAction and mounted from the
 * per-row Edit buttons in its delivery history; it has no button of its own.
 * The row it edits arrives as the `delivery` argument and is parked in the
 * form, because form fields cannot read an action's arguments.
 */
class EditDeliveryAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'editDelivery';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->modalHeading('Edit delivery')
            ->modalWidth('md')
            ->modalSubmitActionLabel('Save')
            // fillForm() is not usable here: the row has to be resolved and the
            // permission checked before the form is filled, and only one mount
            // hook can be registered.
            ->mountUsing(function (?Schema $schema, Order $record, array $arguments): void {
                $delivery = self::delivery($record, $arguments['delivery'] ?? null);

                if (! $delivery || ! (auth()->user()?->can('update_delivery_order') ?? false)) {
                    throw new Halt;
                }

                $schema?->fill([
                    'delivery_id' => $delivery->getKey(),
                    'carrier' => $delivery->carrier,
                    'tracking_number' => $delivery->tracking_number,
                    'shipped_date' => $delivery->shipped_date?->toDateString(),
                    'delivered_date' => $delivery->delivered_date?->toDateString(),
                    'proof_of_delivery' => $delivery->proof_of_delivery,
                    'notes' => $delivery->notes,
                ]);
            })
            ->form([
                Hidden::make('delivery_id'),

                TextInput::make('carrier')
                    ->label('Carrier')
                    ->placeholder('e.g. JNE, J&T, SiCepat'),

                TextInput::make('tracking_number')
                    ->label('Tracking Number')
                    ->placeholder('Enter tracking number'),

                DatePicker::make('shipped_date')
                    ->label('Shipped Date'),

                DatePicker::make('delivered_date')
                    ->label('Delivered Date')
                    ->afterOrEqual('shipped_date'),

                TextInput::make('proof_of_delivery')
                    ->label('Proof of Delivery URL')
                    ->placeholder('https://...'),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3),
            ])
            ->action(function (array $data, Order $record): void {
                $delivery = self::delivery($record, $data['delivery_id'] ?? null);

                if (! $delivery) {
                    return;
                }

                $delivery->update([
                    'carrier' => $data['carrier'] ?? null,
                    'tracking_number' => $data['tracking_number'] ?? null,
                    'shipped_date' => $data['shipped_date'] ?? null,
                    'delivered_date' => $data['delivered_date'] ?? null,
                    'proof_of_delivery' => $data['proof_of_delivery'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);
            })
            ->successNotificationTitle('Delivery updated');
    }

    /**
     * The delivery this action was mounted for. Scoped through the order so an
     * id belonging to a different order cannot be written through it.
     */
    private static function delivery(Order $order, mixed $id): ?DeliveryStatus
    {
        if (! filled($id)) {
            return null;
        }

        return $order->deliveryStatuses()->whereKey($id)->first();
    }
}
