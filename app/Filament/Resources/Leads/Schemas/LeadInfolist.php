<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Users\UserResource;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Lead Details')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('customer_name')
                            ->label('Customer')
                            ->weight('bold')
                            ->url(fn ($record): ?string => $record->customer
                                ? CustomerResource::getUrl('view', ['record' => $record->customer])
                                : null)
                            ->openUrlInNewTab(),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => ucfirst($state))
                            ->color(fn (string $state): string => match ($state) {
                                'converted' => 'success',
                                'disqualified' => 'danger',
                                'contacted' => 'info',
                                default => 'gray',
                            }),
                        TextEntry::make('priority')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                        TextEntry::make('email')
                            ->copyable(),
                        TextEntry::make('phone')
                            ->copyable(),
                        TextEntry::make('collaborators.name')
                            ->label('Assigned To')
                            ->listWithLineBreaks(false)
                            ->placeholder('Unassigned'),
                        TextEntry::make('creator.name')
                            ->label('Created By')

                            ->url(fn ($record): ?string => $record->creator
                                ? UserResource::getUrl('view', ['record' => $record->creator])
                                : null)
                            ->openUrlInNewTab(),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime('d M Y H:i'),
                    ]),

                Section::make('Products')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('leadProductItems')
                            ->label('Lead product items')
                            ->columns(4)
                            ->label('')
                            ->table([
                                RepeatableEntry\TableColumn::make('Product'),
                                RepeatableEntry\TableColumn::make('Principal'),
                                RepeatableEntry\TableColumn::make('Quantity'),
                                RepeatableEntry\TableColumn::make('Unit Price'),
                            ])
                            ->schema([
                                TextEntry::make('product.name')
                                    ->weight('bold'),
                                TextEntry::make('product.principal.name'),
                                TextEntry::make('quantity'),
                                TextEntry::make('unit_price')
                                    ->money('IDR'),
                            ])
                            ->placeholder('No products yet'),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('estimated_revenue')
                                    ->label('Expected Revenue')
                                    ->money('IDR'),
                                TextEntry::make('expected_closing_date')
                                    ->label('Estimated Closing Date')
                                    ->date('d M Y'),
                                TextEntry::make('total_value')
                                    ->label('Total Value')
                                    ->money('IDR')
                                    ->state(fn ($record): float => $record->leadProductItems->sum(fn ($item): float => (float) $item->quantity * (float) $item->unit_price)),
                            ]),
                    ]),

                Grid::make(2)
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Opportunities')
                            ->schema([
                                RepeatableEntry::make('opportunities')
                                    ->label('')
                                    ->schema([
                                        TextEntry::make('opportunity_code')
                                            ->label('Code')
                                            ->weight('bold'),
                                        TextEntry::make('title')
                                            ->label('Title'),
                                        TextEntry::make('stage')
                                            ->label('Stage')
                                            ->badge(),
                                    ])
                                    ->columns(3)
                                    ->placeholder('No opportunities yet'),
                            ]),

                        Section::make('Notes')
                            ->schema([
                                TextEntry::make('notes')
                                    ->hiddenLabel()
                                    ->placeholder('-')
                                    ->markdown(),
                            ]),
                    ]),

            ]);
    }
}
