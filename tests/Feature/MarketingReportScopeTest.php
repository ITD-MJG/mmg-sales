<?php

use App\DTOs\ReportFilterData;
use App\Models\Department;
use App\Models\Order;
use App\Models\Principal;
use App\Models\Role;
use App\Models\User;
use App\Services\PrincipalScopeService;
use App\Services\Reports\SalesReportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
    Role::findOrCreate('Marketing Manager');
});

function marketingReportUser(array $principalIds = []): User
{
    $marketing = Department::factory()->create(['name' => Department::MARKETING]);
    $user = User::factory()->create(['department_id' => $marketing->id]);
    $user->assignRole('Marketing Manager');

    if ($principalIds !== []) {
        $user->principals()->attach($principalIds);
    }

    return $user;
}

function reportFilters(?array $principalIds = null): ReportFilterData
{
    return ReportFilterData::fromArray([
        'start_date' => now()->startOfYear(),
        'end_date' => now()->endOfYear(),
    ], $principalIds);
}

it('keeps another principal out of a marketing user report', function () {
    $assigned = Principal::factory()->create();
    $other = Principal::factory()->create();
    $user = marketingReportUser([$assigned->id]);

    $assignedOrder = Order::factory()->create([
        'principal_id' => $assigned->id,
        'created_by' => User::factory()->create()->id,
        'order_date' => now(),
        'total_amount' => 1000,
    ]);
    Order::factory()->create([
        'principal_id' => $other->id,
        'created_by' => User::factory()->create()->id,
        'order_date' => now(),
        'total_amount' => 5000,
    ]);

    $filters = ReportFilterData::fromArray([
        'start_date' => now()->startOfYear(),
        'end_date' => now()->endOfYear(),
    ], PrincipalScopeService::principalIds($user));

    $report = app(SalesReportService::class)->generate($filters);

    expect($report->totalRevenue)->toEqual($assignedOrder->total_amount);
});

it('limits the export rows to the assigned principals', function () {
    $assigned = Principal::factory()->create();
    $other = Principal::factory()->create();
    $user = marketingReportUser([$assigned->id]);

    $assignedOrder = Order::factory()->create([
        'principal_id' => $assigned->id,
        'created_by' => User::factory()->create()->id,
        'order_date' => now(),
    ]);
    Order::factory()->create([
        'principal_id' => $other->id,
        'created_by' => User::factory()->create()->id,
        'order_date' => now(),
    ]);

    $rows = app(SalesReportService::class)->getExportData(
        ReportFilterData::fromArray([
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
        ], PrincipalScopeService::principalIds($user)),
    );

    expect(collect($rows)->pluck('Order Number')->all())->toBe([$assignedOrder->order_number]);
});

it('leaves a report unrestricted when no principal scope is given', function () {
    $first = Principal::factory()->create();
    $second = Principal::factory()->create();

    Order::factory()->create(['principal_id' => $first->id, 'order_date' => now(), 'total_amount' => 1000]);
    Order::factory()->create(['principal_id' => $second->id, 'order_date' => now(), 'total_amount' => 2000]);

    $report = app(SalesReportService::class)->generate(reportFilters());

    expect($report->totalRevenue)->toEqual(3000.0);
});

it('returns nothing for a marketing user with no principal assignment', function () {
    Order::factory()->create(['principal_id' => Principal::factory()->create()->id, 'order_date' => now()]);

    $report = app(SalesReportService::class)->generate(reportFilters([]));

    expect($report->totalRevenue)->toEqual(0.0);
});

it('resolves the principal scope from the authenticated marketing user', function () {
    $assigned = Principal::factory()->create();
    $user = marketingReportUser([$assigned->id]);

    $assignedOrder = Order::factory()->create([
        'principal_id' => $assigned->id,
        'order_date' => now(),
        'total_amount' => 1000,
    ]);
    Order::factory()->create([
        'principal_id' => Principal::factory()->create()->id,
        'order_date' => now(),
        'total_amount' => 5000,
    ]);

    $this->actingAs($user);

    $report = app(SalesReportService::class)->generate(reportFilters());

    expect($report->totalRevenue)->toEqual($assignedOrder->total_amount);
});
