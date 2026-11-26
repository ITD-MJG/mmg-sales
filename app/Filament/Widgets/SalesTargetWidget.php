<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Target;
use App\Services\PrincipalScopeService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use NumberFormatter;

class SalesTargetWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        $wholeCompany = ! $user->isSalesOrMarketingDepartment();

        $targets = Target::query()->where('year', now()->year);
        if (! $wholeCompany) {
            $targets->where('user_id', $user->id);
        }

        $monthlyTarget = (float) (clone $targets)
            ->where('month', now()->month)
            ->sum('monthly_target');
        // A year has no annual row any more; its annual target is the sum of
        // the rep's (or, company-wide, everyone's) monthly targets.
        $annualTarget = (float) $targets->sum('monthly_target');

        // Attributed through `sales_id`: orders arrive from an import run by an
        // administrator, so `created_by` credits the importer, not the rep.
        $revenue = function () use ($user, $wholeCompany) {
            $query = PrincipalScopeService::orders(Order::query(), $user)
                ->whereYear('order_date', now()->year);

            return $wholeCompany ? $query : $query->where('sales_id', $user->id);
        };

        $annualRevenue = (float) $revenue()->sum('total_amount');
        $monthlyRevenue = (float) $revenue()
            ->whereMonth('order_date', now()->month)
            ->sum('total_amount');

        $annualAchievement = $annualTarget > 0 ? ($annualRevenue / $annualTarget) * 100 : 0;
        $monthlyAchievement = $monthlyTarget > 0 ? ($monthlyRevenue / $monthlyTarget) * 100 : 0;

        $lowerAchievement = min($annualAchievement, $monthlyAchievement);
        $color = 'danger';
        if ($lowerAchievement >= 100) {
            $color = 'success';
        } elseif ($lowerAchievement >= 75) {
            $color = 'warning';
        }

        $formatter = new NumberFormatter('id_ID', NumberFormatter::CURRENCY);

        return [
            Stat::make('Monthly Target', $formatter->formatCurrency($monthlyTarget, 'IDR'))
                ->description(now()->translatedFormat('F Y'))
                ->descriptionIcon('heroicon-m-calendar'),
            Stat::make('Annual Target', $formatter->formatCurrency($annualTarget, 'IDR'))
                ->description(now()->year.' target')
                ->descriptionIcon('heroicon-m-flag'),
            Stat::make('Target Achievement', number_format($monthlyAchievement, 1).'% monthly / '.number_format($annualAchievement, 1).'% annual')
                ->color($color),
        ];
    }
}
