<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\FormatsKpiValues;
use App\Models\Target;
use App\Services\DashboardMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * The rep's own numbers for the period, above every chart on the Sales
 * dashboard. Scoped to the signed-in user alone, matching how a target row is
 * held per rep per month.
 */
class SalesKpiWidget extends StatsOverviewWidget
{
    use FormatsKpiValues;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        $metrics = app(DashboardMetricsService::class);
        $ownRecords = [$user->id];
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $monthlyTarget = (float) Target::query()
            ->where('user_id', $user->id)
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->value('monthly_target');

        $annualTarget = (float) Target::query()
            ->where('user_id', $user->id)
            ->where('year', now()->year)
            ->sum('monthly_target');

        $monthlyRevenue = $metrics->revenue($monthStart, $monthEnd, $ownRecords);
        $annualRevenue = $metrics->revenue(now()->startOfYear(), now()->endOfYear(), $ownRecords);

        $monthlyOrders = $metrics->orderCount($monthStart, $monthEnd, $ownRecords);
        $lastMonthOrders = $metrics->orderCount(
            $monthStart->copy()->subMonthNoOverflow(),
            $monthStart->copy()->subMonthNoOverflow()->endOfMonth(),
            $ownRecords,
        );

        $winRate = $metrics->winRate($monthStart, $monthEnd, $ownRecords);

        return [
            Stat::make('Revenue This Month', $this->money($monthlyRevenue))
                ->description($this->targetDescription($monthlyRevenue, $monthlyTarget, 'monthly'))
                ->descriptionIcon('heroicon-m-flag')
                ->color($this->achievementColor($monthlyRevenue, $monthlyTarget)),

            Stat::make('Revenue This Year', $this->money($annualRevenue))
                ->description($this->targetDescription($annualRevenue, $annualTarget, 'annual'))
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($this->achievementColor($annualRevenue, $annualTarget)),

            Stat::make('Orders This Month', number_format($monthlyOrders))
                ->description($this->orderTrend($monthlyOrders, $lastMonthOrders))
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color($monthlyOrders > $lastMonthOrders ? 'success' : 'gray'),

            Stat::make('Win Rate This Month', $winRate['rate'] === null ? '—' : number_format($winRate['rate'], 1).'%')
                ->description($winRate['won'].' won / '.$winRate['lost'].' lost')
                ->descriptionIcon('heroicon-m-trophy')
                ->color($this->winRateColor($winRate['rate'])),
        ];
    }
}
