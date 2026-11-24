<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AnalyticsDashboard;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\SalesDashboard;
use Filament\Widgets\Widget;

class DashboardShortcutsWidget extends Widget
{
    protected string $view = 'filament.widgets.dashboard-shortcuts';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 0;

    /**
     * Filament renders widgets lazily by default, which would fetch this group
     * in a second request and show a loading placeholder on every dashboard
     * change. It is the only way to reach the dashboards now, so render it
     * inline instead.
     */
    protected static bool $isLazy = false;

    /**
     * Set by the dashboard hosting the widget, so the group can mark the page
     * being viewed. It is a property rather than a URL comparison because
     * Livewire re-renders the widget on its own endpoint, where the request
     * path says nothing about the page the user is on.
     *
     * @var class-string|null
     */
    public ?string $currentDashboard = null;

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
     * Homepage comes first so the group reads home -> sales -> analytics.
     *
     * Only offer pages the user is allowed to open. AnalyticsDashboard is gated
     * on the view_sales_reports permission, so listing it unconditionally would
     * hand most users a forbidden link.
     *
     * @return list<array{label: string, description: string, url: string, icon: string, active: bool}>
     */
    protected function getDestinations(): array
    {
        $destinations = [];

        if (Dashboard::canAccess()) {
            $destinations[] = [
                'label' => 'Homepage',
                'description' => 'Lead status, expected revenue and monthly orders',
                'url' => Dashboard::getUrl(),
                'icon' => 'heroicon-m-home',
                'active' => $this->currentDashboard === Dashboard::class,
            ];
        }

        if (SalesDashboard::canAccess()) {
            $destinations[] = [
                'label' => 'Sales',
                'description' => 'Targets, leaderboard and rep performance',
                'url' => SalesDashboard::getUrl(),
                'icon' => 'heroicon-m-chart-bar-square',
                'active' => $this->currentDashboard === SalesDashboard::class,
            ];
        }

        if (AnalyticsDashboard::canAccess()) {
            $destinations[] = [
                'label' => 'Analytics',
                'description' => 'Revenue, principal and territory breakdowns',
                'url' => AnalyticsDashboard::getUrl(),
                'icon' => 'heroicon-m-presentation-chart-line',
                'active' => $this->currentDashboard === AnalyticsDashboard::class,
            ];
        }

        return $destinations;
    }
}
