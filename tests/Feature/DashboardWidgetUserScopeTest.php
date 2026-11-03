<?php

namespace Tests\Feature;

use App\Filament\Widgets\EstimateRevenue;
use App\Filament\Widgets\MonthlyOrderChart;
use App\Filament\Widgets\RevenueByPrincipalChart;
use App\Filament\Widgets\TopSellingProductsChart;
use App\Filament\Widgets\TopVisitedCustomersChart;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Principal;
use App\Models\Product;
use App\Models\Territory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role as SpatieRole;
use ReflectionMethod;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * The dashboard widgets must resolve against the signed-in user.
 *
 * Two defects are covered here:
 *
 * - Lead widgets matched only `created_by`, so a rep a lead was assigned to
 *   (`lead_collaborators`, which replaced the retired `assigned_to` column)
 *   saw nothing. Affects Expected Revenue by Closing Month and Top Visited
 *   Leads.
 * - Order widgets kept `sales_id IS NULL` rows visible to everyone, so every
 *   sales user saw the whole company's unattributed order value. Unattributed
 *   orders now belong to nobody and only global viewers see them.
 */
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-01-15 09:00:00'));

    $this->seed(RolesAndPermissionsSeeder::class);

    foreach (['Sales Representative', 'Area Sales Manager', 'Regional Sales Manager'] as $role) {
        SpatieRole::findOrCreate($role);
    }

    $this->territory = Territory::factory()->create(['name' => 'North']);

    $this->rep = User::factory()->create(['name' => 'Rep', 'territory_id' => $this->territory->id]);
    $this->rep->assignRole('Sales Representative');

    $this->asm = User::factory()->create(['name' => 'ASM', 'territory_id' => $this->territory->id]);
    $this->asm->assignRole('Area Sales Manager');

    $this->rsm = User::factory()->create(['name' => 'RSM', 'territory_id' => $this->territory->id]);
    $this->rsm->assignRole('Regional Sales Manager');

    $this->outsider = User::factory()->create(['name' => 'Outsider']);
    $this->outsider->assignRole('Sales Representative');

    $this->superAdmin = User::factory()->create(['name' => 'Root']);
    $this->superAdmin->assignRole('Super Admin');

    $this->principal = Principal::factory()->create(['name' => 'Princ A']);
    $this->customer = Customer::factory()->create(['name' => 'Cust A']);
});

afterEach(function () {
    $this->travelBack();
});

function scopeWidgetData(string $widgetClass): array
{
    $method = new ReflectionMethod($widgetClass, 'getData');
    $method->setAccessible(true);

    return $method->invoke(new $widgetClass);
}

function scopeDatasetSum(array $data, int $dataset = 0): float
{
    return array_sum($data['datasets'][$dataset]['data']);
}

/** A lead owned by someone else, with the given user assigned as collaborator. */
function assignedLead(User $owner, User $assignee, float $revenue = 100.0): Lead
{
    $lead = Lead::factory()->create([
        'created_by' => $owner->id,
        'estimated_revenue' => $revenue,
        'expected_closing_date' => '2026-06-01',
        'status' => 'new',
    ]);

    $lead->collaborators()->attach($assignee->id, ['added_by' => $owner->id]);

    return $lead;
}

it('counts expected revenue for a rep the lead is assigned to, not just the creator', function () {
    assignedLead($this->outsider, $this->rep);

    actingAs($this->rep);
    expect(scopeDatasetSum(scopeWidgetData(EstimateRevenue::class), 0))->toBe(100.0);
});

it('does not count expected revenue for an unrelated rep', function () {
    assignedLead($this->outsider, $this->rep);

    actingAs($this->outsider);
    expect(scopeDatasetSum(scopeWidgetData(EstimateRevenue::class), 0))->toBe(100.0);
});

it('gives ASM and RSM the territory leads regardless of creator', function () {
    assignedLead($this->outsider, $this->rep, 40.0);

    actingAs($this->asm);
    expect(scopeDatasetSum(scopeWidgetData(EstimateRevenue::class), 0))->toBe(40.0);

    actingAs($this->rsm);
    expect(scopeDatasetSum(scopeWidgetData(EstimateRevenue::class), 0))->toBe(40.0);
});

it('hides an unattributed order from every sales role but not from a global viewer', function () {
    $order = Order::factory()->create([
        'sales_id' => null,
        'total_amount' => 5_000_000,
        'order_date' => '2026-01-10',
        'created_by' => $this->outsider->id,
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'principal_id' => $this->principal->id,
        'quantity' => 3,
        'subtotal' => 999,
    ]);

    foreach ([$this->rep, $this->asm, $this->rsm, $this->outsider] as $salesUser) {
        actingAs($salesUser);

        expect(scopeDatasetSum(scopeWidgetData(MonthlyOrderChart::class)))->toBe(0.0)
            ->and(scopeDatasetSum(scopeWidgetData(RevenueByPrincipalChart::class)))->toBe(0.0)
            ->and(scopeDatasetSum(scopeWidgetData(TopSellingProductsChart::class)))->toBe(0.0);
    }

    // Global viewers keep full visibility, including unattributed rows.
    actingAs($this->superAdmin);
    expect(scopeDatasetSum(scopeWidgetData(MonthlyOrderChart::class)))->toBe(5.0)
        ->and(scopeDatasetSum(scopeWidgetData(RevenueByPrincipalChart::class)))->toBe(999.0)
        ->and(scopeDatasetSum(scopeWidgetData(TopSellingProductsChart::class)))->toBe(3.0);
});

it('shows a rep only their own attributed order value', function () {
    foreach ([[$this->rep, 2_000_000], [$this->outsider, 9_000_000]] as [$owner, $total]) {
        Order::factory()->create([
            'sales_id' => $owner->id,
            'total_amount' => $total,
            'order_date' => '2026-01-10',
            'created_by' => $owner->id,
        ]);
    }

    actingAs($this->rep);
    expect(scopeDatasetSum(scopeWidgetData(MonthlyOrderChart::class)))->toBe(2.0);

    actingAs($this->outsider);
    expect(scopeDatasetSum(scopeWidgetData(MonthlyOrderChart::class)))->toBe(9.0);

    // The outsider has no territory, so a territory manager does not pick them
    // up — only the rep in their own territory counts.
    actingAs($this->asm);
    expect(scopeDatasetSum(scopeWidgetData(MonthlyOrderChart::class)))->toBe(2.0);
});

it('counts visits on a lead the viewer is assigned to, even when a colleague logged them', function () {
    $lead = assignedLead($this->outsider, $this->rep);

    Activity::factory()->create([
        'lead_id' => $lead->id,
        'user_id' => $this->outsider->id,
        'customer_id' => $this->customer->id,
        'performed_at' => '2026-01-10',
    ]);

    actingAs($this->rep);
    expect(scopeWidgetData(TopVisitedCustomersChart::class)['labels'])->not->toBeEmpty();
});

it('does not count visits on a lead the viewer has no claim to', function () {
    $lead = assignedLead($this->outsider, $this->rep);

    Activity::factory()->create([
        'lead_id' => $lead->id,
        'user_id' => $this->outsider->id,
        'customer_id' => $this->customer->id,
        'performed_at' => '2026-01-10',
    ]);

    $stranger = User::factory()->create(['name' => 'Stranger']);
    $stranger->assignRole('Sales Representative');

    actingAs($stranger);
    expect(scopeWidgetData(TopVisitedCustomersChart::class)['labels'])->toBe([]);
});
