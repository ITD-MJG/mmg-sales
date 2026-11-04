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

                TextColumn::make('visit_started_at')
                    ->label('Start')
                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('H:i') : '-')
                    ->sortable(),

                TextColumn::make('visit_ended_at')
                    ->label('End')
                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('H:i') : '-')
                    ->sortable(),

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

                TextColumn::make('purpose')
                    ->label('Purpose')
                    ->limit(40)
                    ->wrap()
                    ->searchable(),

                TextColumn::make('expectations')
                    ->label('Expectations')
                    ->limit(40)
                    ->wrap()
                    ->searchable(),

                TextColumn::make('targets')
                    ->label('Targets')
                    ->limit(40)
                    ->wrap()
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(fn ($record): string => $record->subject)
                    ->modalWidth('7xl'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->orderBy('performed_at', 'desc'));
    }
}
