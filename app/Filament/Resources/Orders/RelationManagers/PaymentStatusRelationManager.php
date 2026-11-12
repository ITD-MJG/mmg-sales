<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentStatusRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentStatuses';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'partial' => 'info',
                        'full' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('amount')
                    ->money('IDR'),
                TextColumn::make('notes'),
                TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->schema([
                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'partial' => 'Partial',
                                'full' => 'Full',
                            ])
                            ->required(),
                        TextInput::make('amount')
                            ->numeric()
                            ->prefix('IDR'),
                        Textarea::make('notes'),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
