<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Traits\HasVisibilityScope;
use App\Models\Lead;
use Carbon\Carbon;
use Filament\Actions\ViewAction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The open leads whose closing date has already passed or falls inside the next
 * month, latest closing date first.
 *
 * Closed leads are excluded: the widget answers "what is slipping", and a deal
 * that is already won cannot slip.
 */
class LeadsAtRiskWidget extends TableWidget
{
    use HasVisibilityScope;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    protected static ?string $heading = 'Leads At Risk';

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $query = Lead::query()
                    ->whereNotIn('status', ['closed_won', 'closed_lost'])
                    ->whereNotNull('expected_closing_date')
                    ->where('expected_closing_date', '<=', now()->addDays(30)->toDateString())
                    ->orderByDesc('expected_closing_date');

                self::applyLeadVisibilityScope($query);

                return $query;
            })
            ->columns([
                Tables\Columns\TextColumn::make('lead_code')
                    ->label('Lead')
                    ->description(fn (Lead $record): ?string => $record->title)
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->limit(20),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Lead::STATUS_LABELS[$state] ?? ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'decision_maker' => 'primary',
                        'negotiate' => 'warning',
                        'convince' => 'info',
                        'quotation' => 'primary',
                        'hold' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('expected_closing_date')
                    ->label('Expected Close')
                    ->formatStateUsing(fn ($state): string => Carbon::parse($state)->translatedFormat('d M Y'))
                    ->description(fn ($state): string => Carbon::parse($state)->diffForHumans())
                    ->color(fn ($state): string => Carbon::parse($state)->isPast() ? 'danger' : 'warning'),

                Tables\Columns\TextColumn::make('estimated_revenue')
                    ->label('Expected Revenue')
                    ->money('IDR')
                    ->placeholder('—'),
            ])
            ->actions([
                ViewAction::make()
                    ->url(fn (Lead $record): string => LeadResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn (Lead $record): string => LeadResource::getUrl('view', ['record' => $record]))
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5);
    }
}
