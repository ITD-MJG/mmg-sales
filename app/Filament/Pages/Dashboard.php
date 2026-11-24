<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardShortcutsWidget;
use App\Filament\Widgets\LeadsAtRiskWidget;
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
     * The figures, then the list of leads that are slipping. The funnel and the
     * monthly order trend moved to the Sales dashboard, where the rep reading
     * them can act on the number.
     *
     * Scoped through the visibility contract, so a rep lands on their own
     * territory rather than the company total.
     */
    public function getWidgets(): array
    {
        return [
            new WidgetConfiguration(DashboardShortcutsWidget::class, ['currentDashboard' => static::class]),
            OrganizationKpiWidget::class,
            LeadsAtRiskWidget::class,
        ];
    }
}
