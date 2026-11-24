<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Traits\HasVisibilityScope;
use App\Models\Lead;
use Carbon\Carbon;
use Filament\Actions\ViewAction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UpcomingLeadsWidget extends BaseWidget
{
    use HasVisibilityScope;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Leads Nearing Completion';

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $query = Lead::query()
                    // Open leads only: a won or lost deal cannot be "nearing
                    // completion", and closed rows crowded the five slots.
                    ->whereNotIn('status', ['closed_won', 'closed_lost'])
                    ->whereNotNull('expected_closing_date')
                    ->where('expected_closing_date', '>=', now()->startOfDay())
                    ->where('expected_closing_date', '<=', now()->addDays(30)->toDateString())
                    ->orderBy('expected_closing_date', 'asc')
                    ->limit(5);

                self::applyLeadVisibilityScope($query);

                return $query;
            })
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
                Tables\Columns\TextColumn::make('expected_closing_date')
                    ->label('Est. Finish')
                    ->date('d M Y')
                    ->formatStateUsing(fn ($state) => $state ? strtoupper(Carbon::parse($state)->translatedFormat('d M Y')) : '-')
                    ->color(fn ($state) => Carbon::parse($state)->isPast() ? 'danger' : (Carbon::parse($state)->diffInDays(now()) <= 7 ? 'warning' : 'success')),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Lead::STATUS_LABELS[$state] ?? ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'closed_won' => 'success',
                        'closed_lost' => 'danger',
                        'decision_maker' => 'primary',
                        'negotiate' => 'warning',
                        'convince' => 'info',
                        'quotation' => 'primary',
                        'qualification' => 'warning',
                        'prospect' => 'info',
                        'hold' => 'gray',
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
