<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\HasVisibilityScope;
use App\Models\User;
use App\Services\DashboardMetricsService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;

/**
 * Revenue per rep against the target rows held for them, over the same twelve
 * months the dashboard charts cover.
 *
 * Reps with a target but no revenue are listed too: an empty row is the finding,
 * and a leaderboard built only from orders cannot show it.
 */
class SalesRepRevenueLeaderboardWidget extends TableWidget
{
    use HasVisibilityScope;

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 7;

    protected static ?string $heading = 'Revenue by Sales Rep';

    public function getTableRecordKey(Model|array $record): string
    {
        return (string) (is_array($record) ? $record['user_id'] : $record->user_id);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->rows())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Sales Rep')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('revenue')
                    ->label('Revenue')
                    ->money('IDR')
                    ->alignRight(),

                Tables\Columns\TextColumn::make('orders')
                    ->label('Orders')
                    ->alignRight(),

                Tables\Columns\TextColumn::make('target')
                    ->label('Target')
                    ->money('IDR')
                    ->alignRight(),

                Tables\Columns\TextColumn::make('achievement')
                    ->label('Achieved')
                    ->badge()
                    ->formatStateUsing(fn (?float $state): string => $state === null ? '—' : number_format($state, 1).'%')
                    ->color(fn (?float $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 100 => 'success',
                        $state >= 75 => 'warning',
                        default => 'danger',
                    })
                    ->alignRight(),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5);
    }

    /**
     * @return list<array{user_id: int, name: string, revenue: float, orders: int, target: float, achievement: float|null}>
     */
    private function rows(): array
    {
        $metrics = app(DashboardMetricsService::class);
        $months = $metrics->recentMonths();
        $visible = self::visibleUserIds();

        $revenue = $metrics->revenueByRep($months[0], now()->endOfMonth(), $visible)->keyBy('user_id');
        $targets = $metrics->targetTotalsByUser($months, $visible);

        $userIds = $revenue->keys()->merge($targets->keys())->unique()->values();
        $names = User::query()->whereIn('id', $userIds)->pluck('name', 'id');

        return $userIds
            ->map(function (int $id) use ($revenue, $targets, $names): array {
                $sold = (float) ($revenue[$id]['revenue'] ?? 0);
                $target = (float) ($targets[$id] ?? 0);

                return [
                    'user_id' => $id,
                    'name' => (string) ($names[$id] ?? 'User #'.$id),
                    'revenue' => $sold,
                    'orders' => (int) ($revenue[$id]['orders'] ?? 0),
                    'target' => $target,
                    'achievement' => $target > 0 ? ($sold / $target) * 100 : null,
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }
}
