<?php

namespace Tests\Feature;

use App\Filament\Pages\AnalyticsDashboard;
use App\Filament\Widgets\MonthlyOrderChart;
use App\Filament\Widgets\Reports\RevenueByPrincipalWidget;
use App\Filament\Widgets\Reports\SalesReportStatsWidget;
use App\Filament\Widgets\RevenueByPrincipalChart;
use App\Filament\Widgets\TopVisitedCustomersChart;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));
    seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');
    actingAs($this->admin);
});

afterEach(function () {
    $this->travelBack();
});

/**
 * @param  array<string, mixed>  $filters
 * @return array<string, Stat>
 */
function reportStats(array $filters): array
{
    $stats = (function () use ($filters): array {
        $this->pageFilters = $filters;

        return $this->getStats();
    })->call(app(SalesReportStatsWidget::class));

    return collect($stats)->keyBy(fn (Stat $stat): string => (string) $stat->getLabel())->all();
}

it('opens the analytics dashboard on the trailing twelve months', function () {
    $page = Livewire::test(AnalyticsDashboard::class);

    expect($page->get('filters'))->toMatchArray([
        'start_date' => now()->subMonths(11)->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
    ]);
});

it('gives the reports widgets the page period, so one filter moves every panel', function () {
    Order::factory()->create(['order_date' => '2026-06-10', 'total_amount' => 1000]);
    Order::factory()->create(['order_date' => '2025-06-10', 'total_amount' => 500]);

    $thisMonth = reportStats([
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
    ]);

    $lastYear = reportStats([
        'start_date' => '2025-06-01',
        'end_date' => '2025-06-30',
    ]);

    expect($thisMonth['Total Orders']->getValue())->toBe('1')
        ->and($lastYear['Total Orders']->getValue())->toBe('1');
});

it('reads the analytics period from the dashboard filters rather than the calendar year', function () {
    Order::factory()->create(['order_date' => '2026-03-10', 'total_amount' => 1000]);

    $stats = reportStats([
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    expect($stats['Total Orders']->getValue())->toBe('1');
});

it('keeps the dashboards free of per-dashboard charts that duplicate the reports', function () {
    $widgets = array_map(
        fn (string|WidgetConfiguration $widget): string => $widget instanceof WidgetConfiguration ? $widget->widget : $widget,
        (new AnalyticsDashboard)->getWidgets(),
    );

    expect($widgets)
        ->not->toContain(MonthlyOrderChart::class)
        ->not->toContain(RevenueByPrincipalChart::class)
        ->not->toContain(TopVisitedCustomersChart::class)
        ->toContain(RevenueByPrincipalWidget::class);
});
