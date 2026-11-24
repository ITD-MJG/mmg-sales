<?php

namespace Tests\Feature;

use App\Filament\Widgets\OrganizationKpiWidget;
use App\Filament\Widgets\SalesKpiWidget;
use App\Filament\Widgets\SalesRepRevenueLeaderboardWidget;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Target;
use App\Models\Territory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));
    seed(RolesAndPermissionsSeeder::class);

    $this->rep = User::factory()->create(['name' => 'Rep One']);
    $this->rep->assignRole('Sales Staff');

    $this->otherRep = User::factory()->create(['name' => 'Rep Two']);
    $this->otherRep->assignRole('Sales Staff');

    Target::create(['user_id' => $this->rep->id, 'year' => 2026, 'month' => 6, 'monthly_target' => 2000]);
});

afterEach(function () {
    $this->travelBack();
});

/**
 * @return array<string, Stat>
 */
function kpiStats(string $widgetClass): array
{
    $stats = (function (): array {
        return $this->getStats();
    })->call(app($widgetClass));

    return collect($stats)->keyBy(fn (Stat $stat): string => (string) $stat->getLabel())->all();
}

it('shows the rep their own revenue against their own monthly target', function () {
    Order::factory()->create([
        'sales_id' => $this->rep->id,
        'order_date' => '2026-06-10',
        'total_amount' => 1000,
    ]);

    actingAs($this->rep);
    $stats = kpiStats(SalesKpiWidget::class);

    expect($stats)->toHaveKeys(['Revenue This Month', 'Revenue This Year', 'Orders This Month', 'Win Rate This Month'])
        ->and($stats['Revenue This Month']->getDescription())->toContain('50.0%')
        ->and($stats['Orders This Month']->getValue())->toBe('1');
});

it('leaves another rep out of the personal KPI row', function () {
    Order::factory()->create([
        'sales_id' => $this->otherRep->id,
        'order_date' => '2026-06-10',
        'total_amount' => 1000,
    ]);

    actingAs($this->rep);
    $stats = kpiStats(SalesKpiWidget::class);

    expect($stats['Orders This Month']->getValue())->toBe('0');
});

it('reports an em dash instead of a percentage when nothing was decided', function () {
    actingAs($this->rep);
    $stats = kpiStats(SalesKpiWidget::class);

    expect($stats['Win Rate This Month']->getValue())->toBe('—')
        ->and($stats['Win Rate This Month']->getDescription())->toBe('0 won / 0 lost');
});

it('counts a decided lead in the win rate', function () {
    Lead::factory()->create(['created_by' => $this->rep->id, 'status' => 'closed_won', 'updated_at' => '2026-06-05']);
    Lead::factory()->create(['created_by' => $this->rep->id, 'status' => 'closed_lost', 'updated_at' => '2026-06-05']);

    actingAs($this->rep);
    $stats = kpiStats(SalesKpiWidget::class);

    expect($stats['Win Rate This Month']->getValue())->toBe('50.0%')
        ->and($stats['Win Rate This Month']->getDescription())->toBe('1 won / 1 lost');
});

it('sums the visible team into the organisation KPI row', function () {
    $territory = Territory::factory()->create();

    $manager = User::factory()->create(['territory_id' => $territory->id]);
    $manager->assignRole('Area Sales Manager');

    $teamMate = User::factory()->create(['territory_id' => $territory->id]);
    $teamMate->assignRole('Sales Staff');

    Order::factory()->create(['sales_id' => $teamMate->id, 'order_date' => '2026-06-10', 'total_amount' => 700]);
    Order::factory()->create(['sales_id' => $this->otherRep->id, 'order_date' => '2026-06-10', 'total_amount' => 5000]);

    actingAs($manager);
    $stats = kpiStats(OrganizationKpiWidget::class);

    // Only the territory's own order counts, so the outsider's 5000 stays out.
    expect($stats['Orders This Month']->getValue())->toBe('1');
});

it('shows every rep in the leaderboard including one with a target and no revenue', function () {
    Target::create(['user_id' => $this->otherRep->id, 'year' => 2026, 'month' => 6, 'monthly_target' => 4000]);

    Order::factory()->create([
        'sales_id' => $this->rep->id,
        'order_date' => '2026-06-10',
        'total_amount' => 1000,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    actingAs($admin);

    $rows = (function (): array {
        return $this->rows();
    })->call(app(SalesRepRevenueLeaderboardWidget::class));

    $byName = collect($rows)->keyBy('name');

    expect($byName)->toHaveKeys(['Rep One', 'Rep Two'])
        ->and($byName['Rep One']['revenue'])->toBe(1000.0)
        ->and($byName['Rep One']['achievement'])->not->toBeNull()
        ->and($byName['Rep Two']['revenue'])->toBe(0.0)
        ->and($byName['Rep Two']['achievement'])->toBe(0.0);
});

it('renders the leaderboard with a rep who sold nothing', function () {
    Target::create(['user_id' => $this->otherRep->id, 'year' => 2026, 'month' => 6, 'monthly_target' => 4000]);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    actingAs($admin);

    Livewire::test(SalesRepRevenueLeaderboardWidget::class)
        ->assertOk()
        ->assertSee('Rep Two')
        ->assertSee('0.0%');
});
