<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer Information')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Customer Name')
                            ->weight('bold'),
                        TextEntry::make('type')
                            ->label('Type')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                        TextEntry::make('classification')
                            ->label('Tier')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state))),
                        TextEntry::make('tax_number')
                            ->label('Tax Number'),
                        TextEntry::make('credit_limit')
                            ->money('IDR'),
                        TextEntry::make('payment_terms')
                            ->label('Payment Terms'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'inactive' => 'danger',
                            }),
                        TextEntry::make('max_contact_persons')
                            ->label('Max Contacts')
                            ->formatStateUsing(fn ($state): string => $state === null ? 'Unlimited' : $state),
                    ]),

                Section::make('Location & Contact')
                    ->columns(6)
                    ->schema([
                        TextEntry::make('city'),
                        TextEntry::make('state')
                            ->label('Province'),
                        TextEntry::make('postal_code'),
                        TextEntry::make('country'),
                        TextEntry::make('email')
                            ->copyable(),
                        TextEntry::make('phone'),
                        TextEntry::make('address')
                            ->columnSpan(3),
                        TextEntry::make('website')
                            ->columnSpan(3)
                            ->url(fn ($state) => $state, true),
                    ]),

            ]);
    }
}
