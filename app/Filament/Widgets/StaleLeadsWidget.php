<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Traits\HasVisibilityScope;
use App\Models\Lead;
use App\Services\PrincipalScopeService;
use Carbon\Carbon;
use Filament\Actions\ViewAction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Open leads nobody has touched for a fortnight, longest silence first.
 *
 * `last_contacted_at` is written by the activity observer, so a lead that has
 * never had an activity logged carries no date at all. Those sort first: a lead
 * that was never called is a bigger gap than one that went quiet.
 */
class StaleLeadsWidget extends TableWidget
{
    use HasVisibilityScope;

    private const STALE_AFTER_DAYS = 14;

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 6;

    protected static ?string $heading = 'Leads Gone Quiet';

    public function table(Table $table): Table
    {
        $threshold = now()->subDays(self::STALE_AFTER_DAYS);

        return $table
            ->query(function () use ($threshold) {
                $query = PrincipalScopeService::leads(Lead::query(), auth()->user())
                    ->whereNotIn('status', ['closed_won', 'closed_lost'])
                    ->where(function ($stale) use ($threshold): void {
                        $stale->whereNull('last_contacted_at')
                            ->orWhere('last_contacted_at', '<', $threshold);
                    })
                    ->orderByRaw('last_contacted_at IS NULL DESC')
                    ->orderBy('last_contacted_at');

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
                    ->color('gray'),

                Tables\Columns\TextColumn::make('last_contacted_at')
                    ->label('Last Contact')
                    // Filament skips the formatter for a null state, so the
                    // never-contacted case is the placeholder rather than a
                    // branch inside formatStateUsing().
                    ->formatStateUsing(fn ($state): string => Carbon::parse($state)->translatedFormat('d M Y'))
                    ->placeholder('Never contacted')
                    ->description(fn ($state): ?string => $state ? Carbon::parse($state)->diffForHumans() : null)
                    ->color(fn ($state): string => $state && Carbon::parse($state)->lessThan(now()->subDays(30))
                        ? 'danger'
                        : 'warning'),
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
