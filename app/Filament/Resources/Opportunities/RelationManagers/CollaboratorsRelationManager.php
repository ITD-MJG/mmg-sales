<?php

namespace App\Filament\Resources\Opportunities\RelationManagers;

use App\Models\User;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CollaboratorsRelationManager extends RelationManager
{
    protected static string $relationship = 'collaborators';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pivot.added_by')
                    ->label('Added By')
                    ->getStateUsing(fn ($record) => User::find($record->pivot->added_by)?->name ?? 'Unknown'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Add Collaborator')
                    ->visible(function () {
                        $ownerRecord = $this->getOwnerRecord();

                        return auth()->id() === $ownerRecord->created_by;
                    }),
            ])
            ->actions([
                DetachAction::make()
                    ->label('Remove')
                    ->visible(function () {
                        $ownerRecord = $this->getOwnerRecord();

                        return auth()->id() === $ownerRecord->created_by;
                    }),
            ]);
    }
}
