<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use Carbon\Carbon;
use Filament\Actions\ViewAction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UpcomingLeadsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Leads Nearing Completion';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Lead::query()
                    ->whereNotNull('estimated_completion_date')
                    ->where('estimated_completion_date', '>=', now()->startOfDay())
                    ->orderBy('estimated_completion_date', 'asc')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Lead Title')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer'),
                Tables\Columns\TextColumn::make('estimated_revenue')
                    ->label('Expected Revenue')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('estimated_completion_date')
                    ->label('Est. Finish')
                    ->date('d M Y')
                    ->formatStateUsing(fn ($state) => $state ? strtoupper(Carbon::parse($state)->translatedFormat('d M Y')) : '-')
                    ->color(fn ($state) => Carbon::parse($state)->isPast() ? 'danger' : (Carbon::parse($state)->diffInDays(now()) <= 7 ? 'warning' : 'success')),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'gray',
                        'in_process' => 'info',
                        'prospecting' => 'info',
                        'qualification' => 'warning',
                        'proposal' => 'primary',
                        'negotiation' => 'warning',
                        'closed_won' => 'success',
                        'closed_lost' => 'danger',
                        'qualified' => 'primary',
                        'proposal' => 'warning',
                        'negotiation' => 'warning',
                        'won' => 'success',
                        'lost' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->actions([
                ViewAction::make()
                    ->url(fn (?Lead $record): ?string => $record ? LeadResource::getUrl('view', ['record' => $record]) : null),
            ])
            ->recordUrl(
                fn (?Lead $record): ?string => $record ? LeadResource::getUrl('view', ['record' => $record]) : null,
            );
    }
}
