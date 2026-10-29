<?php

namespace App\Filament\Widgets;

use App\Models\Principal;
use App\Services\PrincipalMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use NumberFormatter;

class PrincipalStatsWidget extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        if (! $this->record instanceof Principal) {
            return [];
        }

        $totals = PrincipalMetricsService::totals($this->record, Auth::user());

        return [
            Stat::make('Total Orders', number_format($totals['orders']))
                ->description('Orders containing this principal\'s products')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('primary'),
            Stat::make('Total Leads', number_format($totals['leads']))
                ->description('Leads listing this principal\'s products')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),
            Stat::make('Total Actual Sales', $this->money($totals['actual_sales']))
                ->description('Order value booked on those orders')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Total Expected Revenue', $this->money($totals['expected_revenue']))
                ->description('Expected revenue of those leads')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('warning'),
        ];
    }

    private function money(float $amount): string
    {
        $formatter = new NumberFormatter('id_ID', NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 0);

        return $formatter->formatCurrency($amount, 'IDR');
    }
}
