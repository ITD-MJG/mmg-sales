<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardShortcutsWidget;
use App\Filament\Widgets\LeadsAtRiskWidget;
use App\Filament\Widgets\LeadStatusChart;
use App\Filament\Widgets\MonthlyOrderChart;
use App\Filament\Widgets\OrganizationKpiWidget;
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

    /**
     * The figures first, then the two trend charts, then the list of leads that
     * are slipping. Scoped through the visibility contract, so a rep lands on
     * their own territory rather than the company total.
     */
    public function getWidgets(): array
    {
        return [
            new WidgetConfiguration(DashboardShortcutsWidget::class, ['currentDashboard' => static::class]),
            OrganizationKpiWidget::class,
            LeadStatusChart::class,
            MonthlyOrderChart::class,
            LeadsAtRiskWidget::class,
        ];
    }
}
