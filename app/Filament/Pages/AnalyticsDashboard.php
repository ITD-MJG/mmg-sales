<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardShortcutsWidget;
use App\Filament\Widgets\MonthlyOrderChart;
use App\Filament\Widgets\RevenueByPrincipalChart;
use App\Filament\Widgets\RevenueByTerritoryChart;
use App\Filament\Widgets\TopSellingProductsChart;
use App\Filament\Widgets\TopVisitedCustomersChart;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\WidgetConfiguration;

class AnalyticsDashboard extends BaseDashboard
{
    protected static string $routePath = '/analytics';

    protected static ?string $title = 'Analytics';

    protected static ?string $navigationLabel = 'Analytics';

    protected static ?int $navigationSort = 0;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_sales_reports') ?? false;
    }

    public function getColumns(): int|array
    {
        return 3;
    }

    public function getWidgets(): array
    {
        return [
            new WidgetConfiguration(DashboardShortcutsWidget::class, ['currentDashboard' => static::class]),
            MonthlyOrderChart::class,
            RevenueByPrincipalChart::class,
            RevenueByTerritoryChart::class,
            TopSellingProductsChart::class,
            TopVisitedCustomersChart::class,
        ];
    }
}
