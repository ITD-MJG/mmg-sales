<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardShortcutsWidget;
use App\Filament\Widgets\EstimateRevenue;
use App\Filament\Widgets\LeadStatusChart;
use App\Filament\Widgets\MonthlyOrderChart;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\WidgetConfiguration;

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
            new WidgetConfiguration(DashboardShortcutsWidget::class, ['currentDashboard' => static::class]),
            LeadStatusChart::class,
            EstimateRevenue::class,
            MonthlyOrderChart::class,
        ];
    }
}
