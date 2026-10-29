<?php

namespace App\Filament\Resources\Principals\Tables;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\Principal;
use App\Services\PrincipalMetricsService;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PrincipalOrdersTable
{
    public static function query(Principal $principal): Builder
    {
        return PrincipalMetricsService::ordersQuery($principal)
            ->with(['customer:id,name', 'sales:id,name'])
            ->orderByDesc('order_date');
    }

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
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('End Customer')
                    ->searchable()
                    ->sortable()
                    ->limit(20)
                    ->tooltip(fn (?string $state): ?string => $state),

                TextColumn::make('sales.name')
                    ->label('Sales')
                    ->searchable()
                    ->sortable()
                    ->limit(16)
                    ->toggleable(),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable()
                    ->summarize(Sum::make()->money('IDR')->label('Total')),
            ])
            ->filters([
                SelectFilter::make('jual_kso')
                    ->label('Jual / KSO')
                    ->options([
                        'Jual' => 'Jual',
                        'KSO' => 'KSO',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5);
    }
}
