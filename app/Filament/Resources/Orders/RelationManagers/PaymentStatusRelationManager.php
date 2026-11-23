<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Models\PaymentStatus;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentStatusRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentStatuses';

    public function table(Table $table): Table
    {
        return $table
            // By id, matching Order::latestPaymentStatus: created_at is only
            // second-precision, so same-second payments would sort arbitrarily.
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('effective_date')
                    ->label('Effective Date')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'partial' => 'info',
                        'full' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('amount')
                    ->money('IDR'),
                TextColumn::make('notes'),
                TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->schema([
                        TextInput::make('amount')
                            ->numeric()
                            ->prefix('IDR')
                            ->required()
                            ->minValue(1)
                            ->maxValue(fn (): float => $this->getOwnerRecord()->remainingBalance())
                            ->helperText(fn (): string => 'Remaining balance: IDR '
                                .number_format($this->getOwnerRecord()->remainingBalance(), 0, ',', '.'))
                            ->validationMessages([
                                'max' => 'The amount cannot exceed the order\'s remaining balance.',
                            ]),
                        DatePicker::make('effective_date')
                            ->default(now()->toDateString())
                            ->required(),
                        Textarea::make('notes'),
                    ])
                    // Status is never typed here either; it follows from the amounts.
                    ->mutateDataUsing(function (array $data): array {
                        $order = $this->getOwnerRecord();

                        $data['status'] = PaymentStatus::deriveStatus(
                            $order->paidAmount() + (float) $data['amount'],
                            (float) $order->total_amount,
                        );

                        return $data;
                    }),
            ])
            ->recordActions([
                // Each row's status is a snapshot of the running total, so an
                // amount change invalidates every row after it. Resync re-derives
                // the whole chain instead of leaving the later labels stale.
                EditAction::make()
                    ->after(fn () => PaymentStatus::resyncStatuses($this->getOwnerRecord())),
                DeleteAction::make()
                    ->after(fn () => PaymentStatus::resyncStatuses($this->getOwnerRecord())),
            ]);
    }
}
