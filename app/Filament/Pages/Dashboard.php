<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\LeadStatusChart;
use App\Filament\Widgets\MonthlyRevenueTrendChart;
use App\Filament\Widgets\RevenueByPrincipalChart;
use App\Filament\Widgets\TopSalesRepresentativeVisitsWidget;
use App\Filament\Widgets\TopSellingProductsChart;
use App\Filament\Widgets\TopVisitedCustomersWidget;
use App\Filament\Widgets\TopVisitedLeadsChart;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -2;

    public function getColumns(): int|array
    {
        return 3;
    }

    public function getWidgets(): array
    {
        return [
            LeadStatusChart::class,
            MonthlyRevenueTrendChart::class,
            RevenueByPrincipalChart::class,
            TopSellingProductsChart::class,
            TopVisitedLeadsChart::class,
            TopVisitedCustomersWidget::class,
            TopSalesRepresentativeVisitsWidget::class,
        ];
    }
}
