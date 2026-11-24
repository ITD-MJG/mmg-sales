<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\HasVisibilityScope;
use App\Filament\Widgets\Concerns\FormatsKpiValues;
use App\Services\DashboardMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The homepage headline row: the figures anyone landing on the dashboard needs
 * before reading a chart. Scoped through the record-visibility contract, so a
 * rep sees their territory and a global viewer sees everything.
 */
class OrganizationKpiWidget extends StatsOverviewWidget
{
    use FormatsKpiValues;
    use HasVisibilityScope;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $visible = self::visibleUserIds();

        $metrics = app(DashboardMetricsService::class);
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $lastMonth = $monthStart->copy()->subMonthNoOverflow();

        $revenue = $metrics->revenue($monthStart, $monthEnd, $visible);
        $monthlyTarget = (float) $metrics->targetTotalsByUser([$monthStart], $visible)->sum();

        $orders = $metrics->orderCount($monthStart, $monthEnd, $visible);
        $lastMonthOrders = $metrics->orderCount($lastMonth, $lastMonth->copy()->endOfMonth(), $visible);

        $winRate = $metrics->winRate($monthStart, $monthEnd, $visible);
        $openLeads = $metrics->openLeadCount($visible);

        return [
            Stat::make('Revenue This Month', $this->money($revenue))
                ->description($this->targetDescription($revenue, $monthlyTarget, 'monthly'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($this->achievementColor($revenue, $monthlyTarget)),

            Stat::make('Open Pipeline', $this->money($metrics->openPipeline($visible)))
                ->description($openLeads.' open leads')
                ->descriptionIcon('heroicon-m-funnel')
                ->color('gray'),

            Stat::make('Orders This Month', number_format($orders))
                ->description($this->orderTrend($orders, $lastMonthOrders))
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color($orders > $lastMonthOrders ? 'success' : 'gray'),

            Stat::make('Win Rate This Month', $winRate['rate'] === null ? '—' : number_format($winRate['rate'], 1).'%')
                ->description($winRate['won'].' won / '.$winRate['lost'].' lost')
                ->descriptionIcon('heroicon-m-trophy')
                ->color($this->winRateColor($winRate['rate'])),
        ];
    }
}
