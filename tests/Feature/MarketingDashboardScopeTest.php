<?php

use App\Models\Department;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Principal;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\DashboardMetricsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
    Role::findOrCreate('Marketing Staff');
});

function dashboardMarketingUser(array $principalIds = []): User
{
    $marketing = Department::factory()->create(['name' => Department::MARKETING]);
    $user = User::factory()->create(['department_id' => $marketing->id]);
    $user->assignRole('Marketing Staff');

    if ($principalIds !== []) {
        $user->principals()->attach($principalIds);
    }

    return $user;
}

it('counts only assigned-principal orders for a marketing user', function () {
    $assigned = Principal::factory()->create();
    $user = dashboardMarketingUser([$assigned->id]);

    Order::factory()->create([
        'principal_id' => $assigned->id,
        'order_date' => now(),
        'total_amount' => 1000,
        'created_by' => User::factory()->create()->id,
        'sales_id' => User::factory()->create()->id,
    ]);
    Order::factory()->create([
        'principal_id' => Principal::factory()->create()->id,
        'order_date' => now(),
        'total_amount' => 5000,
        'created_by' => User::factory()->create()->id,
        'sales_id' => User::factory()->create()->id,
    ]);

    $this->actingAs($user);

    $metrics = app(DashboardMetricsService::class);

    expect($metrics->revenue(now()->startOfDay(), now()->endOfDay(), null))->toEqual(1000.0)
        ->and($metrics->orderCount(now()->startOfDay(), now()->endOfDay(), null))->toBe(1);
});

it('counts only own and assigned-principal leads for a marketing user', function () {
    $assigned = Principal::factory()->create();
    $user = dashboardMarketingUser([$assigned->id]);

    Lead::factory()->create(['created_by' => $user->id, 'status' => 'new']);
    $assignedLead = Lead::factory()->create(['created_by' => User::factory()->create()->id, 'status' => 'new']);
    $assignedLead->leadProductItems()->create([
        'product_id' => Product::factory()->create(['principal_id' => $assigned->id])->id,
        'principal_id' => $assigned->id,
        'quantity' => 1,
        'unit_price' => 100,
    ]);
    Lead::factory()->create(['created_by' => User::factory()->create()->id, 'status' => 'new']);

    $this->actingAs($user);

    expect(app(DashboardMetricsService::class)->openLeadCount(null))->toBe(2);
});

it('keeps the global dashboard for a super admin', function () {
    Role::findOrCreate('Super Admin');
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    Order::factory()->create(['order_date' => now(), 'total_amount' => 1000, 'principal_id' => Principal::factory()->create()->id]);
    Order::factory()->create(['order_date' => now(), 'total_amount' => 2000, 'principal_id' => Principal::factory()->create()->id]);

    $this->actingAs($admin);

    expect(app(DashboardMetricsService::class)->revenue(now()->startOfDay(), now()->endOfDay(), null))->toEqual(3000.0);
});
