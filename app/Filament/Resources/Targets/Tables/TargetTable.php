<?php

namespace App\Filament\Resources\Targets\Tables;

use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TargetTable
{
    public static function configure(Table $table): Table
    {
        return $table

            ->columns([
                TextColumn::make('user.name')
                    ->label('Sales Representative')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('year')
                    ->label('Year')
                    ->sortable(),
                TextColumn::make('month')
                    ->label('Month')
                    ->formatStateUsing(fn ($state) => $state ? Carbon::create()->month($state)->format('F') : 'N/A')
                    ->sortable(),
                TextColumn::make('annual_target')
                    ->label('Annual Target')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('monthly_target')
                    ->label('Monthly Target')
                    ->money('IDR')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
