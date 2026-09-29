<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product Specifications')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Product Name')
                            ->weight('bold')
                            ->columnSpan(2),
                        TextEntry::make('internal_code')
                            ->label('Internal Code'),
                        TextEntry::make('principal.name')
                            ->label('Principal'),
                        TextEntry::make('category')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),
                        TextEntry::make('unit_price')
                            ->label('Price')
                            ->money('IDR'),
                        TextEntry::make('ecatalog_price')
                            ->label('E-Catalog Price')
                            ->money('IDR'),
                        TextEntry::make('unit_of_measure')
                            ->label('UoM'),
                        TextEntry::make('is_active')
                            ->label('Active')
                            ->badge()
                            ->color(fn ($state) => $state ? 'success' : 'danger')
                            ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive'),
                    ]),

                Section::make('Description')
                    ->schema([
                        TextEntry::make('description')
                            ->hiddenLabel()
                            ->markdown(),
                    ]),
            ]);
    }
}
