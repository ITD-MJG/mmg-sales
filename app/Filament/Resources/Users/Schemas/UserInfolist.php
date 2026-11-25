<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile Information')
                    ->columns(6)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name')
                            ->weight('bold'),
                        TextEntry::make('email')
                            ->copyable(),
                        TextEntry::make('department.name'),
                        TextEntry::make('position.name'),
                        TextEntry::make('territory.name')
                            ->label('Primary Territory'),
                        TextEntry::make('manager.name')
                            ->label('Reports To'),
                        TextEntry::make('principals.name')
                            ->label('Principal Responsibility')
                            ->placeholder('None')
                            ->columnSpanFull(),
                    ]),

                Section::make('Latest Interactions')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('activities')
                            ->label('')
                            ->schema([
                                TextEntry::make('performed_at')
                                    ->label('Date')
                                    ->dateTime('d M Y H:i'),
                                TextEntry::make('customer.name')
                                    ->label('Customer'),
                                TextEntry::make('type')
                                    ->badge(),
                                TextEntry::make('subject'),
                            ])
                            ->columns(4)
                            ->state(fn (User $record) => $record->activities()->latest()->limit(5)->get()),
                    ]),
            ]);
    }
}
