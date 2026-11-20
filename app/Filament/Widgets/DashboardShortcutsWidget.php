<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AnalyticsDashboard;
use App\Filament\Pages\SalesDashboard;
use Filament\Widgets\Widget;

class DashboardShortcutsWidget extends Widget
{
    protected string $view = 'filament.widgets.dashboard-shortcuts';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return auth()->check()
            && (SalesDashboard::canAccess() || AnalyticsDashboard::canAccess());
    }

    protected function getViewData(): array
    {
        return [
            'destinations' => $this->getDestinations(),
        ];
    }

    /**
     * Only offer pages the user is allowed to open. AnalyticsDashboard is gated
     * on the view_sales_reports permission, so listing it unconditionally would
     * hand most users a forbidden link.
     *
     * @return list<array{label: string, description: string, url: string, icon: string}>
     */
    protected function getDestinations(): array
    {
        $destinations = [];

        if (SalesDashboard::canAccess()) {
            $destinations[] = [
                'label' => 'Sales',
                'description' => 'Targets, leaderboard and rep performance',
                'url' => SalesDashboard::getUrl(),
                'icon' => 'heroicon-m-chart-bar-square',
            ];
        }

        if (AnalyticsDashboard::canAccess()) {
            $destinations[] = [
                'label' => 'Analytics',
                'description' => 'Revenue, principal and territory breakdowns',
                'url' => AnalyticsDashboard::getUrl(),
                'icon' => 'heroicon-m-presentation-chart-line',
            ];
        }

        return $destinations;
    }
}
