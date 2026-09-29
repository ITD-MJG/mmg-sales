<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                        Select::make('category')
                            ->options([
                                'medical_equipment' => 'Medical equipment',
                                'pharmaceutical' => 'Pharmaceutical',
                                'consumables' => 'Consumables',
                                'diagnostics' => 'Diagnostics',
                                'other' => 'Other',
                            ])
                            ->default('other')
                            ->required(),
                        Toggle::make('is_active')
                            ->required(),
                    ])
                    ->columns(4),

                Section::make('Pricing')
                    ->schema([
                        TextInput::make('unit_price')
                            ->required()
                            ->numeric()
                            ->prefix('IDR'),
                        TextInput::make('ecatalog_price')
                            ->label('E-Catalog Price')
                            ->numeric()
                            ->prefix('IDR'),
                    ])
                    ->columns(2),

                Section::make('Supplier & Inventory')
                    ->schema([
                        Select::make('principal_id')
                            ->relationship('principal', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('unit_of_measure')
                            ->required()
                            ->default('pcs'),
                    ])
                    ->columns(2),
            ]);
    }
}
