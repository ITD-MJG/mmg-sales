<?php

namespace App\Filament\Resources\Principals\Tables;

use App\Filament\Resources\Principals\PrincipalResource;
use App\Models\Principal;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PrincipalsTable
{
    public static function configure(Table $table): Table
    {
        return $table

            ->columns([
                TextColumn::make('principal_code')
                    ->label('Principal Code')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Principal Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->url(fn (Principal $record): string => PrincipalResource::getUrl('view', ['record' => $record])),

                TextColumn::make('initial')
                    ->label('Initial')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->date('d M Y')
                    ->formatStateUsing(fn ($state) => strtoupper(Carbon::parse($state)->translatedFormat('d M Y')))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
