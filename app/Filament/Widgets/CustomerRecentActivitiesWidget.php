<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Activities\ActivityResource;
use App\Models\Activity;
use Filament\Tables;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerRecentActivitiesWidget extends TableWidget
{
    public ?Model $record = null;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Activity::query()
                ->where('customer_id', $this->record?->getKey())
                ->latest('performed_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Sales Rep')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('performed_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->limit(30),

                ViewAction::make()
                    ->url(fn (Activity $record): string => ActivityResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5);
    }
}
