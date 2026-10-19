<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use App\Filament\Resources\Activities\Schemas\ActivityInfolist;
use Carbon\Carbon;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    public function infolist(Schema $schema): Schema
    {
        return ActivityInfolist::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject')
            ->columns([
                TextColumn::make('performed_at')
                    ->label('Date')
                    ->formatStateUsing(fn ($state) => $state ? strtoupper(Carbon::parse($state)->translatedFormat('d M Y')) : '-')
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(),

                TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable(),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn ($state) => match ($state) {
                        'Online Meeting', 'In-person Meeting' => 'success',
                        'Call', 'Messaging' => 'info',
                        'Demo', 'Presentation' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('user.name')
                    ->label('Sales Rep')
                    ->searchable(),

                TextColumn::make('outcome')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'Interested' => 'success',
                        'Not Interested' => 'danger',
                        'No Answer' => 'warning',
                        'Need more info' => 'info',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(fn ($record): string => $record->subject)
                    ->modalWidth('7xl'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->orderBy('performed_at', 'desc'));
    }
}
