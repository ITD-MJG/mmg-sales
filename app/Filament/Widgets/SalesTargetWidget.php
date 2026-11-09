<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Target;
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

        $target = Target::where('user_id', $user->id)
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->first();

        $monthlyTarget = (float) ($target->monthly_target ?? 0);
        // A year has no annual row any more; its annual target is the sum of
        // the rep's monthly targets.
        $annualTarget = (float) Target::where('user_id', $user->id)
            ->where('year', now()->year)
            ->sum('monthly_target');

        $annualShippedAmount = Order::query()
            ->where('created_by', $user->id)
            ->whereHas('deliveryStatuses', fn ($q) => $q->whereNotNull('shipped_date'))
            ->whereYear('order_date', now()->year)
            ->sum('total_amount');

        $monthlyShippedAmount = Order::query()
            ->where('created_by', $user->id)
            ->whereHas('deliveryStatuses', fn ($q) => $q->whereNotNull('shipped_date'))
            ->whereYear('order_date', now()->year)
            ->whereMonth('order_date', now()->month)
            ->sum('total_amount');

        $annualAchievement = $annualTarget > 0 ? ($annualShippedAmount / $annualTarget) * 100 : 0;
        $monthlyAchievement = $monthlyTarget > 0 ? ($monthlyShippedAmount / $monthlyTarget) * 100 : 0;

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
