<?php

namespace App\Filament\Resources\ActivityLogs\Tables;


use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(Activity::query())
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->tooltip(fn ($state) => $state)
                    ->searchable(),

                TextColumn::make('log_name')
                    ->label('Log')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'default' => 'gray',
                        default => 'primary',
                    }),

                TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('causer.name')
                    ->label('Caused By')
                    ->searchable(),

                TextColumn::make('subject_type')
                    ->label('Subject Type')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('subject_id')
                    ->label('Subject ID')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('attribute_changes')
                    ->label('Changes')
                    ->limit(30)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('properties')
                    ->label('Properties')
                    ->limit(30)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Log Name')
                    ->options([
                        'default' => 'Default',
                    ]),

                SelectFilter::make('event')
                    ->label('Event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),

                Filter::make('created_at')
                    ->label('Date Range')
                    ->form([
                        \Filament\Forms\Components\DateTimePicker::make('created_from')
                            ->label('From')
                            ->native(false),
                        \Filament\Forms\Components\DateTimePicker::make('created_until')
                            ->label('Until')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn (Builder $query, $date) => $query->where('created_at', '>=', $date))
                            ->when($data['created_until'], fn (Builder $query, $date) => $query->where('created_at', '<=', $date));
                    }),
            ]);

    }
}
