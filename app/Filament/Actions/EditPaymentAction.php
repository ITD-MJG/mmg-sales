<?php

namespace App\Filament\Actions;

use App\Models\Order;
use App\Models\PaymentStatus;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;

/**
 * Corrects a payment that is already recorded.
 *
 * Registered as a modal action of UpdatePaymentAction and mounted from the
 * per-row Edit buttons in its payment history; it has no button of its own.
 * The row it edits arrives as the `payment` argument and is parked in the
 * form, because form fields cannot read an action's arguments.
 */
class EditPaymentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'editPayment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->modalHeading('Edit payment')
            ->modalWidth('md')
            ->modalSubmitActionLabel('Save')
            // fillForm() is not usable here: the row has to be resolved and the
            // permission checked before the form is filled, and only one mount
            // hook can be registered.
            ->mountUsing(function (?Schema $schema, Order $record, array $arguments): void {
                $payment = self::payment($record, $arguments['payment'] ?? null);

                if (! $payment || ! (auth()->user()?->can('update_payment_order') ?? false)) {
                    throw new Halt;
                }

                $schema?->fill([
                    'payment_id' => $payment->getKey(),
                    'amount' => (float) $payment->amount,
                    'effective_date' => $payment->effective_date?->toDateString(),
                    'notes' => $payment->notes,
                ]);
            })
            ->form([
                Hidden::make('payment_id'),

                TextInput::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->prefix('IDR')
                    ->required()
                    ->minValue(1)
                    // The row being edited is given back before the cap is
                    // computed: its own amount cannot block its correction.
                    ->maxValue(fn (Order $record, Get $get): float => self::headroom($record, $get))
                    ->helperText(fn (Order $record, Get $get): string => 'Available to record: IDR '
                        .number_format(self::headroom($record, $get), 0, ',', '.'))
                    ->validationMessages([
                        'max' => 'The amount cannot exceed what is left to record on this order.',
                    ]),

                DatePicker::make('effective_date')
                    ->label('Effective Date')
                    ->required(),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3),
            ])
            ->action(function (array $data, Order $record): void {
                $payment = self::payment($record, $data['payment_id'] ?? null);

                if (! $payment) {
                    return;
                }

                $payment->update([
                    'amount' => $data['amount'],
                    'effective_date' => $data['effective_date'] ?? $payment->effective_date,
                    'notes' => $data['notes'] ?? null,
                ]);

                // Every later row's status is a snapshot of the running total,
                // so one amount change invalidates the whole chain.
                PaymentStatus::resyncStatuses($record);
            })
            ->successNotificationTitle('Payment updated');
    }

    /**
     * The payment this action was mounted for. Scoped through the order so an
     * id belonging to a different order cannot be written through it.
     */
    private static function payment(Order $order, mixed $id): ?PaymentStatus
    {
        if (! filled($id)) {
            return null;
        }

        return $order->paymentStatuses()->whereKey($id)->first();
    }

    /**
     * What this order can still take once the edited payment is discounted.
     */
    private static function headroom(Order $order, Get $get): float
    {
        $payment = self::payment($order, $get('payment_id'));
        $paid = $order->paidAmount();

        // paidAmount() only sums rows that are already partial or full, so a
        // pending row must not be added back a second time.
        $counted = $payment && in_array($payment->status, ['partial', 'full'], true);

        return max(0, (float) $order->total_amount - ($counted ? $paid - (float) $payment->amount : $paid));
    }
}
