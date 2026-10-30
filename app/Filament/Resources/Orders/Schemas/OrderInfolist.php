<?php

namespace App\Filament\Resources\Orders\Schemas;

use Carbon\Carbon;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order Details')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(7)
                            ->schema([
                                TextEntry::make('order_number')
                                    ->label('Order #')
                                    ->weight('bold'),
                                TextEntry::make('reg_inst')
                                    ->label('Type')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'REG' => 'info',
                                        'INST' => 'warning',
                                        'Consumable' => 'success',
                                        default => 'gray',
                                    }),
                                TextEntry::make('sales_type_id')
                                    ->label('Sales Type'),
                                TextEntry::make('jual_kso')
                                    ->label('Jual/KSO'),
                                TextEntry::make('distributor.name')
                                    ->label('Distributor'),
                                TextEntry::make('order_date')
                                    ->label('Date')
                                    ->date('d M Y')
                                    ->formatStateUsing(fn ($state) => strtoupper(Carbon::parse($state)->translatedFormat('d M Y'))),
                                TextEntry::make('po_number')
                                    ->label('PO Number')
                                    ->placeholder('-'),
                                TextEntry::make('payment_method')
                                    ->label('Payment Method'),
                            ]),

                    ]),

                Section::make('Sales Details')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(6)
                            ->schema([
                                TextEntry::make('srPosition.name')
                                    ->label('Sales Rep'),
                                TextEntry::make('spvPosition.name')
                                    ->label('Supervisor'),
                                TextEntry::make('rsmAsmPosition.name')
                                    ->label('RSM/ASM'),
                                TextEntry::make('headPosition.name')
                                    ->label('Head'),
                                TextEntry::make('pmJpmPePosition.name')
                                    ->label('PM/JPM/PE'),
                            ]),
                    ]),

                Section::make('Customer Details')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('customer.name')
                                    ->label('End Customer')
                                    ->weight('bold'),
                            ]),

                        TextEntry::make('billing_address')
                            ->label('Billing Address')
                            ->columnSpan(1),

                        TextEntry::make('shipping_address')
                            ->label('Shipping Address')
                            ->columnSpan(1),
                    ]),

                Section::make('Product Details')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('orderItems')
                            ->schema([
                                Grid::make()
                                    ->columns(4)
                                    ->schema([
                                        TextEntry::make('item.name')
                                            ->label('Item')
                                            ->weight('bold'),
                                        TextEntry::make('quantity')
                                            ->label('Qty'),
                                        TextEntry::make('unit_price')
                                            ->label('Unit Price')
                                            ->money('IDR'),
                                        TextEntry::make('subtotal')
                                            ->label('Line Total')
                                            ->money('IDR'),
                                    ]),
                            ]),
                    ]),

                Section::make('Total')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('subtotal')
                                    ->label('Gross Sales')
                                    ->money('IDR'),
                                TextEntry::make('discount_on')
                                    ->label('Discount')
                                    ->formatStateUsing(fn ($state) => $state ? $state.'%' : '0%'),
                                TextEntry::make('total_amount')
                                    ->label('Net Sales')
                                    ->money('IDR')
                                    ->weight('bold')
                                    ->color('success'),
                            ]),
                    ]),

                Section::make('Additional Notes')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('notes')
                            ->hiddenLabel()
                            ->placeholder('No additional notes.'),
                    ]),
            ]);
    }
}
