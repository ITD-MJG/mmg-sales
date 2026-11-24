<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardShortcutsWidget;
use App\Filament\Widgets\Reports\RevenueByCustomerGroupWidget;
use App\Filament\Widgets\Reports\RevenueByPrincipalWidget;
use App\Filament\Widgets\Reports\RevenueTrendWidget;
use App\Filament\Widgets\Reports\SalesReportStatsWidget;
use App\Filament\Widgets\Reports\TopCustomersWidget;
use App\Filament\Widgets\Reports\TopSalesRepresentativesWidget;
use App\Filament\Widgets\Reports\TopSellingProductsWidget;
use App\Filament\Widgets\Reports\TopTerritoriesWidget;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\WidgetConfiguration;

/**
 * Revenue quality across the whole book, not the per-rep view the Sales
 * dashboard owns.
 *
 * The widgets are the Reports ones rather than dashboard-specific copies: they
 * already read from the report services, and mounting them here means the
 * numbers on this page and on Reports cannot drift apart. They all consume the
 * page filters through `InteractsWithPageFilters`, so the period selected below
 * moves every panel at once.
 */
class AnalyticsDashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = '/analytics';

    protected static ?string $title = 'Analytics';

    protected static ?string $navigationLabel = 'Analytics';

    protected static ?int $navigationSort = 0;

    /**
     * Reached through the dashboard shortcuts widget instead of the sidebar,
     * so the sidebar keeps one dashboard entry rather than three.
     */
    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_sales_reports') ?? false;
    }

    public function mount(): void
    {
        // ReportFilterData falls back to the whole calendar year; the dashboard
        // opens on the trailing twelve months instead, matching its charts.
        $this->filters = [
            'start_date' => now()->subMonths(11)->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ];
    }

    public function getColumns(): int|array
    {
        return 3;
    }

    public function getWidgets(): array
    {
        return [
            new WidgetConfiguration(DashboardShortcutsWidget::class, ['currentDashboard' => static::class]),
            SalesReportStatsWidget::class,
            RevenueTrendWidget::class,
            TopSalesRepresentativesWidget::class,
            TopTerritoriesWidget::class,
            RevenueByCustomerGroupWidget::class,
            RevenueByPrincipalWidget::class,
            TopSellingProductsWidget::class,
            TopCustomersWidget::class,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Period')
                    ->columnSpan(1)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('start_date')
                                    ->label('From')
                                    ->default(now()->subMonths(11)->startOfMonth())
                                    ->required(),
                                DatePicker::make('end_date')
                                    ->label('To')
                                    ->default(now())
                                    ->required(),
                            ]),
                    ]),
            ]);
    }
}
