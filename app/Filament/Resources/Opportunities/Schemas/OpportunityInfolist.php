<?php

namespace App\Filament\Resources\Opportunities\Schemas;

use App\Filament\Widgets\OpportunityRevenueComparisonChart;
use Carbon\Carbon;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OpportunityInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Opportunity Details')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('customer_name')
                            ->label('Customer')
                            ->weight('bold'),
                        TextEntry::make('stage')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => ucfirst($state))
                            ->color(fn (string $state): string => match ($state) {
                                'won' => 'success',
                                'lost' => 'danger',
                                'new' => 'gray',
                                default => 'info',
                            }),
                        TextEntry::make('priority')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                        TextEntry::make('email')
                            ->copyable(),
                        TextEntry::make('phone')
                            ->copyable(),
                        TextEntry::make('estimated_completion_date')
                            ->label('Expected Finish')
                            ->formatStateUsing(fn ($state) => $state ? strtoupper(Carbon::parse($state)->translatedFormat('M Y')) : '-')
                            ->weight('bold'),
                        TextEntry::make('collaborators')
                            ->label('Collaborator')
                            ->getStateUsing(fn ($record) => $record->collaborators->pluck('name')->join(', '))
                            ->placeholder('-'),
                        TextEntry::make('creator.name')
                            ->label('Created By'),
                    ]),

                Grid::make(2)
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Suppliers & Products')
                            ->schema([
                                RepeatableEntry::make('products')
                                    ->label('')
                                    ->schema([
                                        TextEntry::make('principal.name')
                                            ->label('Principal')
                                            ->weight('bold'),
                                        TextEntry::make('name')
                                            ->label('Product'),
                                    ])
                                    ->columns(2),
                            ]),

                        Livewire::make(OpportunityRevenueComparisonChart::class)
                            ->data(fn ($component) => ['record' => $component->getRecord()]),
                    ]),

                Section::make('Activities History')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('activities')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('performed_at')
                                            ->label('Date')
                                            ->dateTime('d M Y H:i'),
                                        TextEntry::make('subject')
                                            ->weight('bold'),
                                        TextEntry::make('type')
                                            ->badge()
                                            ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                                        TextEntry::make('outcome')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'Interested' => 'success',
                                                'Not Interested' => 'danger',
                                                'No Answer' => 'warning',
                                                'Need more info' => 'info',
                                                'Postponed' => 'gray',
                                                default => 'gray',
                                            }),
                                    ]),
                                TextEntry::make('description')
                                    ->markdown(),
                            ])
                            // Sorting logic typically happens at the relationship level
                            // or via getEloquentQuery in the Page.
                            ->columns(1),
                    ]),
            ]);
    }
}
