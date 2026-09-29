<?php

namespace App\Filament\Resources\Leads\Schemas;

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
                            ->weight('bold'),
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
                        TextEntry::make('assignedUser.name')
                            ->label('Assigned To')
                            ->placeholder('Unassigned'),
                        TextEntry::make('creator.name')
                            ->label('Created By'),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime('d M Y H:i'),
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
                            ->columns(1),
                    ]),
            ]);
    }
}
