<?php

namespace App\Filament\Actions;

use App\Models\Order;
use App\Models\PaymentStatus;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

class UpdatePaymentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'updatePayment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Update Payment')
            ->icon(Heroicon::CurrencyDollar)
            ->color('success')
            ->visible(fn () => auth()->user()->can('update_payment_order'))
            ->modalWidth('2xl')
            // Rendered above the form by Filament's modal, so the payment being
            // recorded is entered against the record it extends, not blind to it.
            ->modalContent(fn (Order $record): View => view('filament.actions.payment-history', [
                'order' => $record,
                'payments' => $record->paymentStatuses()->orderByDesc('id')->get(),
                'paid' => $record->paidAmount(),
                'remaining' => $record->remainingBalance(),
            ]))
            ->form([
                TextInput::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->prefix('IDR')
                    ->required()
                    ->minValue(1)
                    // Capped at what is owed: an overpayment would leave the order
                    // paid past its total with no way to take it back.
                    ->maxValue(fn (Order $record): float => $record->remainingBalance())
                    ->helperText(fn (Order $record): string => 'Remaining balance: IDR '
                        .number_format($record->remainingBalance(), 0, ',', '.'))
                    ->validationMessages([
                        'max' => 'The amount cannot exceed the order\'s remaining balance.',
                    ])
                    ->disabled(fn (Order $record): bool => $record->remainingBalance() <= 0)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set, Order $record): void {
                        $set('status', PaymentStatus::deriveStatus(
                            $record->paidAmount() + (float) $state,
                            (float) $record->total_amount,
                        ));
                    }),

                // Display only. The action derives the status again from the
                // stored amounts, so this field can never disagree with them.
                Select::make('status')
                    ->label('Status after this payment')
                    ->options([
                        'pending' => 'Pending',
                        'partial' => 'Partial',
                        'full' => 'Full',
                    ])
                    ->default(fn (Order $record): string => PaymentStatus::deriveStatus(
                        $record->paidAmount(),
                        (float) $record->total_amount,
                    ))
                    ->disabled()
                    ->dehydrated(false),

                DatePicker::make('effective_date')
                    ->label('Effective Date')
                    ->default(now()->toDateString())
                    ->required(),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3),
            ])
            ->action(function (array $data, Order $record): void {
                $paid = $record->paidAmount() + (float) $data['amount'];

                $record->paymentStatuses()->create([
                    'status' => PaymentStatus::deriveStatus($paid, (float) $record->total_amount),
                    'amount' => $data['amount'],
                    'effective_date' => $data['effective_date'] ?? now()->toDateString(),
                    'notes' => $data['notes'] ?? null,
                ]);
            })
            // A fully paid order still opens, to read its history, but offers no
            // submit: the amount field is disabled and there is nothing to add.
            ->modalSubmitAction(fn (Order $record, Action $action) => $record->remainingBalance() > 0 ? $action : false)
            ->successNotificationTitle('Payment status updated');
    }
}
