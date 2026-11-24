<?php

namespace Tests\Feature;

use App\Filament\Pages\AnalyticsDashboard;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\SalesDashboard;
use App\Filament\Widgets\DashboardShortcutsWidget;
use App\Filament\Widgets\EstimateRevenue;
use App\Filament\Widgets\LeadsAtRiskWidget;
use App\Filament\Widgets\LeadStatusChart;
use App\Filament\Widgets\OrganizationKpiWidget;
use App\Filament\Widgets\RecentActivitiesWidget;
use App\Filament\Widgets\Reports\RevenueByCustomerGroupWidget;
use App\Filament\Widgets\Reports\RevenueByPrincipalWidget;
use App\Filament\Widgets\Reports\RevenueTrendWidget;
use App\Filament\Widgets\Reports\SalesReportStatsWidget;
use App\Filament\Widgets\Reports\TopCustomersWidget;
use App\Filament\Widgets\Reports\TopSalesRepresentativesWidget;
use App\Filament\Widgets\Reports\TopSellingProductsWidget;
use App\Filament\Widgets\Reports\TopTerritoriesWidget;
use App\Filament\Widgets\SalesKpiWidget;
use App\Filament\Widgets\SalesRepRevenueLeaderboardWidget;
use App\Filament\Widgets\StaleLeadsWidget;
use App\Filament\Widgets\TargetVsAchievementChart;
use App\Filament\Widgets\UpcomingLeadsWidget;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
});

/**
 * @param  array<string | WidgetConfiguration>  $widgets
 * @return array<string>
 */
function widgetClasses(array $widgets): array
{
    return array_map(
        fn (string|WidgetConfiguration $widget): string => $widget instanceof WidgetConfiguration ? $widget->widget : $widget,
        $widgets,
    );
}

function widgetSets(): array
{
    return [
        'homepage' => [
            'page' => Dashboard::class,
            'widgets' => [
                DashboardShortcutsWidget::class,
                OrganizationKpiWidget::class,
                LeadsAtRiskWidget::class,
            ],
        ],
        'sales' => [
            'page' => SalesDashboard::class,
            'widgets' => [
                DashboardShortcutsWidget::class,
                SalesKpiWidget::class,
                TargetVsAchievementChart::class,
                LeadStatusChart::class,
                EstimateRevenue::class,
                UpcomingLeadsWidget::class,
                StaleLeadsWidget::class,
                RecentActivitiesWidget::class,
                SalesRepRevenueLeaderboardWidget::class,
            ],
        ],
        'analytics' => [
            'page' => AnalyticsDashboard::class,
            'widgets' => [
                DashboardShortcutsWidget::class,
                SalesReportStatsWidget::class,
                RevenueTrendWidget::class,
                TopSalesRepresentativesWidget::class,
                TopTerritoriesWidget::class,
                RevenueByCustomerGroupWidget::class,
                RevenueByPrincipalWidget::class,
                TopSellingProductsWidget::class,
                TopCustomersWidget::class,
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

    expect(widgetClasses((new $page)->getWidgets()))->toBe($widgets);
})->with(fn () => collect(widgetSets())->map(fn (array $set) => [$set['page'], $set['widgets']])->all());

it('hides the Sales dashboard from users without view_sales_dashboard', function () {
    $user = User::factory()->create();
    actingAs($user);

    expect(SalesDashboard::canAccess())->toBeFalse();

    $this->get(SalesDashboard::getUrl())->assertForbidden();
});

it('shows the Sales dashboard to users with view_sales_dashboard', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('view_sales_dashboard');
    actingAs($user);

    expect(SalesDashboard::canAccess())->toBeTrue();

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

it('renders dashboard shortcut links to Sales and Analytics for a user with both', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Admin');
    actingAs($user);

    $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertSee(SalesDashboard::getUrl(), escape: false)
        ->assertSee(AnalyticsDashboard::getUrl(), escape: false);
});

it('omits the Analytics shortcut for a user without view_sales_reports', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('view_sales_dashboard');
    actingAs($user);

    expect(DashboardShortcutsWidget::canView())->toBeTrue();

    $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertSee(SalesDashboard::getUrl(), escape: false)
        ->assertDontSee(AnalyticsDashboard::getUrl(), escape: false);
});

it('omits the shortcuts widget for a user with neither dashboard permission', function () {
    $user = User::factory()->create();
    actingAs($user);

    expect(DashboardShortcutsWidget::canView())->toBeFalse();

    $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertDontSee(SalesDashboard::getUrl(), escape: false)
        ->assertDontSee(AnalyticsDashboard::getUrl(), escape: false);
});

it('keeps the Sales and Analytics dashboards out of the sidebar', function () {
    expect(Dashboard::shouldRegisterNavigation())->toBeTrue()
        ->and(SalesDashboard::shouldRegisterNavigation())->toBeFalse()
        ->and(AnalyticsDashboard::shouldRegisterNavigation())->toBeFalse();
});

it('still serves both dashboards at their own URL without a nav item', function (string $page) {
    $user = User::factory()->create();
    $user->assignRole('Super Admin');
    actingAs($user);

    $this->get($page::getUrl())->assertSuccessful();
})->with([
    'sales' => [SalesDashboard::class],
    'analytics' => [AnalyticsDashboard::class],
]);

function shortcutAnchor(string $html, string $url): string
{
    preg_match_all('/<a\b[^>]*>/', $html, $matches);

    foreach ($matches[0] as $anchor) {
        if (str_contains($anchor, 'fi-btn') && str_contains($anchor, 'href="'.$url.'"')) {
            return $anchor;
        }
    }

    return '';
}

it('marks the dashboard being viewed as active in the shortcuts group', function (string $current, string $other) {
    $user = User::factory()->create();
    $user->assignRole('Super Admin');
    actingAs($user);

    $html = Livewire::test(DashboardShortcutsWidget::class, ['currentDashboard' => $current])->html();

    expect(shortcutAnchor($html, $current::getUrl()))
        ->toContain('aria-current="page"')
        ->toContain('fi-color-primary')
        ->and(shortcutAnchor($html, $other::getUrl()))
        ->not->toContain('aria-current')
        ->not->toContain('fi-color-primary');
})->with([
    'on homepage' => [Dashboard::class, SalesDashboard::class],
    'on sales' => [SalesDashboard::class, AnalyticsDashboard::class],
    'on analytics' => [AnalyticsDashboard::class, SalesDashboard::class],
]);

it('lists the homepage ahead of the permission-gated dashboards', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Admin');
    actingAs($user);

    $html = Livewire::test(DashboardShortcutsWidget::class)->html();

    expect($html)->toContain('Homepage')
        ->and(strpos($html, Dashboard::getUrl()))->toBeLessThan(strpos($html, SalesDashboard::getUrl()))
        ->and(strpos($html, SalesDashboard::getUrl()))->toBeLessThan(strpos($html, AnalyticsDashboard::getUrl()));
});

it('leaves every shortcut inactive when no dashboard claims the group', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Admin');
    actingAs($user);

    $html = Livewire::test(DashboardShortcutsWidget::class)->html();

    expect($html)->toContain(Dashboard::getUrl())
        ->toContain(SalesDashboard::getUrl())
        ->toContain(AnalyticsDashboard::getUrl())
        ->not->toContain('aria-current')
        ->not->toContain('fi-color-primary');
});

it('renders the shortcut group inline instead of as a lazy placeholder', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Admin');
    actingAs($user);

    expect(DashboardShortcutsWidget::isLazy())->toBeFalse();

    $html = $this->get(Dashboard::getUrl())->assertSuccessful()->getContent();

    expect($html)->toContain('Homepage')
        ->and(shortcutAnchor($html, Dashboard::getUrl()))->not->toBeEmpty()
        ->and(shortcutAnchor($html, SalesDashboard::getUrl()))->not->toBeEmpty()
        ->and(shortcutAnchor($html, AnalyticsDashboard::getUrl()))->not->toBeEmpty();
});

it('navigates the shortcut group without a full page load', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Admin');
    actingAs($user);

    $html = Livewire::test(DashboardShortcutsWidget::class)->html();

    foreach ([Dashboard::getUrl(), SalesDashboard::getUrl(), AnalyticsDashboard::getUrl()] as $url) {
        expect(shortcutAnchor($html, $url))->toContain('wire:navigate');
    }
});

it('tells the shortcuts group which dashboard hosts it', function (string $page) {
    $configuration = collect((new $page)->getWidgets())
        ->first(fn (string|WidgetConfiguration $widget): bool => ($widget instanceof WidgetConfiguration ? $widget->widget : $widget) === DashboardShortcutsWidget::class);

    expect($configuration)->toBeInstanceOf(WidgetConfiguration::class)
        ->and($configuration->getProperties())->toBe(['currentDashboard' => $page]);
})->with([
    'homepage' => [Dashboard::class],
    'sales' => [SalesDashboard::class],
    'analytics' => [AnalyticsDashboard::class],
]);
