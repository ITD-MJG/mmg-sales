<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Actions\UpdateDeliveryAction;
use App\Filament\Actions\UpdatePaymentAction;
use App\Filament\Traits\HasVisibilityScope;
use App\Models\Order;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersTable
{
    use HasVisibilityScope;

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('order_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->formatStateUsing(fn ($state) => strtoupper(Carbon::parse($state)->translatedFormat('d M Y')))
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('End Customer')
                    ->searchable()
                    ->sortable()
                    ->limit(25)
                    ->tooltip(fn ($state) => $state),

                TextColumn::make('total_amount')
                    ->label('Net Sales')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('latestDeliveryStatus.carrier')
                    ->label('Delivery Status')
                    ->badge()
                    ->formatStateUsing(fn ($state, Order $record): string => match (true) {
                        $record->latestDeliveryStatus?->delivered_date => 'Delivered',
                        $record->latestDeliveryStatus?->shipped_date => 'Shipped',
                        $record->latestDeliveryStatus?->carrier => 'In Transit',
                        default => 'Pending',
                    })
                    ->color(fn ($state, Order $record): string => match (true) {
                        $record->latestDeliveryStatus?->delivered_date => 'success',
                        $record->latestDeliveryStatus?->shipped_date => 'primary',
                        $record->latestDeliveryStatus?->carrier => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('latestPaymentStatus.status')
                    ->label('Payment Status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'full' => 'Paid',
                        'partial' => 'Partial',
                        'pending' => 'Pending',
                        default => ucfirst($state ?? 'Pending'),
                    })
                    ->color(fn ($state): string => match ($state) {
                        'full' => 'success',
                        'partial' => 'warning',
                        'pending' => 'gray',
                        default => 'gray',
                    }),

                // Metadata & Toggleable columns
                TextColumn::make('tahun')
                    ->label('Year')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('bulan')
                    ->label('Month')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('department.name')
                    ->label('Department')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('principal.name')
                    ->label('Principal')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('distributor.name')
                    ->label('Distributor')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('sales_type_id')
                    ->label('Sales Type')
                    ->toggleable(isToggledHiddenByDefault: true),

                // Sales Hierarchy (Hidden by default)
                TextColumn::make('headPosition.name')
                    ->label('Head')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('pmJpmPePosition.name')
                    ->label('PM/JPM/PE')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('rsmAsmPosition.name')
                    ->label('RSM/ASM')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('spvPosition.name')
                    ->label('Supervisor')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('srPosition.name')
                    ->label('SR')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (Order $record) => auth()->user()?->hasRole('Logistics Staff') || self::canModifyRecord($record, 'created_by')),
                UpdateDeliveryAction::make(),
                UpdatePaymentAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->hasRole('Super Admin')),
                ]),
            ]);
    }
}
