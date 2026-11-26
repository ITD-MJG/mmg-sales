<?php

namespace App\Filament\Actions;

use App\Models\Order;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

class UpdateDeliveryAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'updateDelivery';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Update Delivery')
            ->icon(Heroicon::Truck)
            ->color('info')
            ->visible(fn () => auth()->user()->can('update_delivery_order'))
            ->modalWidth('2xl')
            // Rendered above the form so the shipment being recorded is
            // entered against the record it extends, not blind to it.
            ->modalContent(fn (Order $record): View => view('filament.actions.delivery-history', [
                'order' => $record,
                'deliveries' => $record->deliveryStatuses()->orderByDesc('id')->get(),
            ]))
            ->form([
                TextInput::make('carrier')
                    ->label('Carrier')
                    ->placeholder('e.g. JNE, J&T, SiCepat'),
                TextInput::make('tracking_number')
                    ->label('Tracking Number')
                    ->placeholder('Enter tracking number'),
                DatePicker::make('shipped_date')
                    ->label('Shipped Date')
                    ->default(now()->toDateString()),
                DatePicker::make('delivered_date')
                    ->label('Delivered Date')
                    ->afterOrEqual('shipped_date'),
                TextInput::make('proof_of_delivery')
                    ->label('Proof of Delivery URL')
                    ->placeholder('https://...')
                    ->url(),
                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3),
            ])
            ->action(function (array $data, Order $record): void {
                // visible() only hides the button; a crafted mount still
                // reaches this closure, so the permission is re-checked here.
                abort_unless(auth()->user()?->can('update_delivery_order'), 403);

                $record->deliveryStatuses()->create($data);
            })
            // Mounted by name from the per-row buttons in the history table.
            // registerModalActions() keeps them out of the footer, where a
            // button with no delivery to point at would be meaningless.
            ->registerModalActions([
                EditDeliveryAction::make(),
                DeleteDeliveryAction::make(),
            ])
            ->successNotificationTitle('Delivery status updated');
    }
}
