<?php

namespace Tests\Feature;

use App\Filament\Pages\AnalyticsDashboard;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\SalesDashboard;
use App\Filament\Widgets\EstimateRevenue;
use App\Filament\Widgets\LeadStatusChart;
use App\Filament\Widgets\MonthlyOrderChart;
use App\Filament\Widgets\OpportunityStatusChart;
use App\Filament\Widgets\RecentActivitiesWidget;
use App\Filament\Widgets\RevenueByPrincipalChart;
use App\Filament\Widgets\RevenueByTerritoryChart;
use App\Filament\Widgets\SalesRepLeaderboardWidget;
use App\Filament\Widgets\SalesTargetWidget;
use App\Filament\Widgets\TopSalesRepresentativeVisitsWidget;
use App\Filament\Widgets\TopSellingProductsChart;
use App\Filament\Widgets\TopVisitedCustomersChart;
use App\Filament\Widgets\UpcomingLeadsWidget;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
});

function widgetSets(): array
{
    return [
        'homepage' => [
            'page' => Dashboard::class,
            'widgets' => [
                LeadStatusChart::class,
                EstimateRevenue::class,
                MonthlyOrderChart::class,
            ],
        ],
        'sales' => [
            'page' => SalesDashboard::class,
            'widgets' => [
                LeadStatusChart::class,
                EstimateRevenue::class,
                UpcomingLeadsWidget::class,
                SalesTargetWidget::class,
                SalesRepLeaderboardWidget::class,
                RecentActivitiesWidget::class,
                TopSalesRepresentativeVisitsWidget::class,
            ],
        ],
        'analytics' => [
            'page' => AnalyticsDashboard::class,
            'widgets' => [
                MonthlyOrderChart::class,
                RevenueByPrincipalChart::class,
                RevenueByTerritoryChart::class,
                TopSellingProductsChart::class,
                TopVisitedCustomersChart::class,
                OpportunityStatusChart::class,
            ],
        ],
    ];
}

it('renders each dashboard with its widget set', function (string $page, array $widgets) {
    $user = User::factory()->create();
    $user->assignRole('Super Admin');
    actingAs($user);

    $this->get($page::getUrl())
        ->assertSuccessful()
        ->assertSee($page::getNavigationLabel());

    expect((new $page)->getWidgets())->toBe($widgets);
})->with(fn () => collect(widgetSets())->map(fn (array $set) => [$set['page'], $set['widgets']])->all());

it('allows any authenticated user to reach the Sales dashboard', function () {
    $user = User::factory()->create();
    actingAs($user);

    $this->get(SalesDashboard::getUrl())->assertSuccessful();
});

it('hides the Analytics dashboard from users without view_sales_reports', function () {
    $user = User::factory()->create();
    actingAs($user);

    expect(AnalyticsDashboard::canAccess())->toBeFalse();

    $this->get(AnalyticsDashboard::getUrl())->assertForbidden();
});

it('shows the Analytics dashboard to users with view_sales_reports', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('view_sales_reports');
    actingAs($user);

    expect(AnalyticsDashboard::canAccess())->toBeTrue();

    $this->get(AnalyticsDashboard::getUrl())->assertSuccessful();
});
