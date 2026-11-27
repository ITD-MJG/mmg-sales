<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardShortcutsWidget;
use App\Filament\Widgets\EstimateRevenue;
use App\Filament\Widgets\InvoiceVsTargetChart;
use App\Filament\Widgets\LeadStatusChart;
use App\Filament\Widgets\RecentActivitiesWidget;
use App\Filament\Widgets\SalesKpiWidget;
use App\Filament\Widgets\SalesRepRevenueLeaderboardWidget;
use App\Filament\Widgets\SalesRevenueByInvoiceWidget;
use App\Filament\Widgets\StaleLeadsWidget;
use App\Filament\Widgets\TargetVsAchievementChart;
use App\Filament\Widgets\UpcomingLeadsWidget;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\WidgetConfiguration;

class SalesDashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = '/sales';

    protected static ?string $title = 'Sales';

    protected static ?string $navigationLabel = 'Sales';

    protected static ?int $navigationSort = -1;

    /**
     * Reached through the dashboard shortcuts widget instead of the sidebar,
     * so the sidebar keeps one dashboard entry rather than three.
     */
    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_sales_dashboard') ?? false;
    }

    public function getColumns(): int|array
    {
        return 3;
    }

    public function mount(): void
    {
        $this->filters = [
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->toDateString(),
        ];
    }

    /**
     * The rep's own numbers, then the pipeline and forecast charts, then the two
     * lists that say what to do next, then the team ranking.
     */
    public function getWidgets(): array
    {
        return [
            new WidgetConfiguration(DashboardShortcutsWidget::class, ['currentDashboard' => static::class]),
            SalesKpiWidget::class,
            TargetVsAchievementChart::class,
            InvoiceVsTargetChart::class,
            LeadStatusChart::class,
            EstimateRevenue::class,
            SalesRevenueByInvoiceWidget::class,
            UpcomingLeadsWidget::class,
            StaleLeadsWidget::class,
            RecentActivitiesWidget::class,
            SalesRepRevenueLeaderboardWidget::class,
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
                                    ->default(now()->startOfYear())
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
