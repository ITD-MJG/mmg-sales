<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardShortcutsWidget;
use App\Filament\Widgets\EstimateRevenue;
use App\Filament\Widgets\LeadStatusChart;
use App\Filament\Widgets\RecentActivitiesWidget;
use App\Filament\Widgets\SalesRepLeaderboardWidget;
use App\Filament\Widgets\SalesTargetWidget;
use App\Filament\Widgets\TopSalesRepresentativeVisitsWidget;
use App\Filament\Widgets\UpcomingLeadsWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\WidgetConfiguration;

class SalesDashboard extends BaseDashboard
{
    protected static string $routePath = '/sales';

    protected static ?string $title = 'Sales';

    protected static ?string $navigationLabel = 'Sales';

    protected static ?int $navigationSort = -1;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_sales_dashboard') ?? false;
    }

    public function getColumns(): int|array
    {
        return 3;
    }

    public function getWidgets(): array
    {
        return [
            new WidgetConfiguration(DashboardShortcutsWidget::class, ['currentDashboard' => static::class]),
            SalesTargetWidget::class,
            LeadStatusChart::class,
            EstimateRevenue::class,
            UpcomingLeadsWidget::class,
            SalesRepLeaderboardWidget::class,
            RecentActivitiesWidget::class,
            TopSalesRepresentativeVisitsWidget::class,
        ];
    }
}
