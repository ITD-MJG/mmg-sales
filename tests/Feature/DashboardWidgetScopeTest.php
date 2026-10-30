<?php

namespace Tests\Feature;

use App\Filament\Widgets\EstimateRevenue;
use App\Filament\Widgets\LeadStatusChart;
use App\Filament\Widgets\MonthlyOrderChart;
use App\Filament\Widgets\RevenueByPrincipalChart;
use App\Filament\Widgets\TopSellingProductsChart;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Principal;
use App\Models\Target;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role as SpatieRole;
use ReflectionMethod;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Dashboard widgets must show only the viewer's own records plus their
 * subordinates'. Orders additionally keep rows with a NULL sales_id visible to
 * everyone: `sales_id` was added on 2026-10-28 and every pre-existing order is
 * unattributed, so a strict scope would blank the order widgets company-wide.
 *
 * Time is frozen to a year boundary so the widgets' rolling 12-month window
 * covers exactly one calendar year, which makes target arithmetic exact.
 */
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-01-15 09:00:00'));

    $this->seed(RolesAndPermissionsSeeder::class);

    // The seeder manages "Sales Staff"; "Sales Representative" is the live
    // production role and the one listed in Role::ownRecordsOnly().
    SpatieRole::findOrCreate('Sales Representative');

    $this->manager = User::factory()->create(['name' => 'Manager']);
    $this->manager->assignRole('Sales Manager');

    $this->subordinate = User::factory()->create([
        'name' => 'Subordinate',
        'manager_id' => $this->manager->id,
    ]);
    $this->subordinate->assignRole('Sales Representative');

    $this->outsider = User::factory()->create(['name' => 'Outsider']);
    $this->outsider->assignRole('Sales Representative');

    $this->superAdmin = User::factory()->create(['name' => 'Root']);
    $this->superAdmin->assignRole('Super Admin');

    $principal = Principal::factory()->create(['name' => 'Princ A']);

    // [sales_id, order total, item qty, item subtotal]
    // Order totals are in the millions because MonthlyOrderChart divides by
    // 1_000_000 before charting.
    $rows = [
        'manager' => [$this->manager->id, 1_000_000, 5, 500],
        'subordinate' => [$this->subordinate->id, 200_000, 2, 200],
        'outsider' => [$this->outsider->id, 4_000_000, 40, 4000],
        'legacy' => [null, 50_000, 1, 50],
    ];

    foreach ($rows as [$salesId, $total, $qty, $subtotal]) {
        $order = Order::factory()->create([
            'sales_id' => $salesId,
            'total_amount' => $total,
            'order_date' => '2026-01-10',
            'created_by' => $this->manager->id,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'principal_id' => $principal->id,
            'quantity' => $qty,
            'subtotal' => $subtotal,
        ]);
    }

    foreach ([
        [$this->manager, 100, 1200],
        [$this->subordinate, 20, 240],
        [$this->outsider, 400, 4800],
    ] as [$owner, $leadRevenue, $annualTarget]) {
        Lead::factory()->create([
            'created_by' => $owner->id,
            'estimated_revenue' => $leadRevenue,
            'expected_closing_date' => '2026-06-01',
            'status' => 'new',
        ]);

        Target::create([
            'user_id' => $owner->id,
            'year' => 2026,
            'month' => null,
            'annual_target' => $annualTarget,
        ]);
    }
});

afterEach(function () {
    $this->travelBack();
});

/** Invoke a widget's protected getData(). */
function widgetData(string $widgetClass): array
{
    $method = new ReflectionMethod($widgetClass, 'getData');
    $method->setAccessible(true);

    return $method->invoke(new $widgetClass);
}

function datasetSum(array $data, int $dataset = 0): float
{
    return array_sum($data['datasets'][$dataset]['data']);
}

it('scopes monthly order value to the manager plus subordinates, keeping legacy rows', function () {
    actingAs($this->manager);
    expect(datasetSum(widgetData(MonthlyOrderChart::class)))->toBe(1.25);

    actingAs($this->subordinate);
    expect(datasetSum(widgetData(MonthlyOrderChart::class)))->toBe(0.25);

    actingAs($this->outsider);
    expect(datasetSum(widgetData(MonthlyOrderChart::class)))->toBe(4.05);

    actingAs($this->superAdmin);
    expect(datasetSum(widgetData(MonthlyOrderChart::class)))->toBe(5.25);
});

it('scopes revenue by principal to the manager plus subordinates, keeping legacy rows', function () {
    actingAs($this->manager);
    expect(datasetSum(widgetData(RevenueByPrincipalChart::class)))->toBe(750.0);

    actingAs($this->subordinate);
    expect(datasetSum(widgetData(RevenueByPrincipalChart::class)))->toBe(250.0);

    actingAs($this->superAdmin);
    expect(datasetSum(widgetData(RevenueByPrincipalChart::class)))->toBe(4750.0);
});

it('scopes top selling products to the manager plus subordinates, keeping legacy rows', function () {
    actingAs($this->manager);
    expect(datasetSum(widgetData(TopSellingProductsChart::class)))->toBe(8.0);

    actingAs($this->subordinate);
    expect(datasetSum(widgetData(TopSellingProductsChart::class)))->toBe(3.0);

    actingAs($this->superAdmin);
    expect(datasetSum(widgetData(TopSellingProductsChart::class)))->toBe(48.0);
});

it('scopes expected revenue leads to the manager plus subordinates', function () {
    actingAs($this->manager);
    expect(datasetSum(widgetData(EstimateRevenue::class), 0))->toBe(120.0);

    actingAs($this->subordinate);
    expect(datasetSum(widgetData(EstimateRevenue::class), 0))->toBe(20.0);

    actingAs($this->outsider);
    expect(datasetSum(widgetData(EstimateRevenue::class), 0))->toBe(400.0);

    actingAs($this->superAdmin);
    expect(datasetSum(widgetData(EstimateRevenue::class), 0))->toBe(520.0);
});

it('scopes expected revenue targets to the manager plus subordinates', function () {
    actingAs($this->manager);
    expect(datasetSum(widgetData(EstimateRevenue::class), 1))->toBe(1440.0);

    actingAs($this->subordinate);
    expect(datasetSum(widgetData(EstimateRevenue::class), 1))->toBe(240.0);

    actingAs($this->superAdmin);
    expect(datasetSum(widgetData(EstimateRevenue::class), 1))->toBe(6240.0);
});

it('gives an own-records-only role its own records and no subordinates', function () {
    // A Sales Representative never sees the records of users below them.
    $lead = User::factory()->create(['manager_id' => $this->subordinate->id]);
    $lead->assignRole('Sales Representative');

    Lead::factory()->create([
        'created_by' => $lead->id,
        'estimated_revenue' => 7,
        'expected_closing_date' => '2026-06-01',
        'status' => 'new',
    ]);

    actingAs($this->subordinate);
    expect(datasetSum(widgetData(EstimateRevenue::class), 0))->toBe(20.0);
});

it('counts only the viewer scope in the lead status funnel', function () {
    Lead::factory()->create([
        'created_by' => $this->outsider->id,
        'estimated_revenue' => 1,
        'expected_closing_date' => '2026-06-01',
        'status' => 'new',
    ]);

    actingAs($this->manager);
    $values = (new LeadStatusChart)->getChartData()['values'];

    // manager 1 + subordinate 1 new leads; the outsider's is excluded.
    expect(array_sum($values))->toBe(2);

    actingAs($this->superAdmin);
    expect(array_sum((new LeadStatusChart)->getChartData()['values']))->toBe(4);
});
