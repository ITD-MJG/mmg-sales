<?php

use App\DTOs\ReportFilterData;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\Order;
use App\Models\Target;
use App\Models\User;
use App\Services\Reports\TargetReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function targetFilters(array $overrides = []): ReportFilterData
{
    return new ReportFilterData(...array_merge([
        'startDate' => Carbon::now()->startOfYear(),
        'endDate' => Carbon::now()->endOfYear(),
    ], $overrides));
}

it('sums monthly targets across the selected range', function () {
    $user = User::factory()->create();

    Target::create(['user_id' => $user->id, 'year' => now()->year, 'month' => 1, 'monthly_target' => 1000000]);
    Target::create(['user_id' => $user->id, 'year' => now()->year, 'month' => 2, 'monthly_target' => 2000000]);

    $result = (new TargetReportService)->generate(targetFilters());

    expect($result->totalTarget)->toBe(3000000.0);
});

it('spreads an annual target evenly when no monthly rows exist', function () {
    $user = User::factory()->create();

    Target::create(['user_id' => $user->id, 'year' => now()->year, 'month' => null, 'annual_target' => 12000000]);

    $result = (new TargetReportService)->generate(targetFilters());

    expect($result->monthlyComparison)->toHaveCount(12);
    expect($result->monthlyComparison->first()['target'])->toBe(1000000.0);
    expect($result->totalTarget)->toBe(12000000.0);
});

it('prefers the monthly row over the annual spread for that month', function () {
    $user = User::factory()->create();

    Target::create(['user_id' => $user->id, 'year' => now()->year, 'month' => null, 'annual_target' => 12000000]);
    Target::create(['user_id' => $user->id, 'year' => now()->year, 'month' => 1, 'monthly_target' => 500000]);

    $result = (new TargetReportService)->generate(targetFilters());

    $january = $result->monthlyComparison->firstWhere('month', 1);
    $february = $result->monthlyComparison->firstWhere('month', 2);

    expect($january['target'])->toBe(500000.0);
    expect($february['target'])->toBe(1000000.0);
});

it('sums estimated revenue of opportunities created in each month', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['type' => 'hospital_clinic']);

    Opportunity::factory()->create([
        'customer_id' => $customer->id,
        'created_by' => $user->id,
        'estimated_revenue' => 750000,
        'created_at' => Carbon::now()->startOfYear()->addDays(5),
    ]);
    Opportunity::factory()->create([
        'customer_id' => $customer->id,
        'created_by' => $user->id,
        'estimated_revenue' => 250000,
        'created_at' => Carbon::now()->startOfYear()->addDays(10),
    ]);

    $result = (new TargetReportService)->generate(targetFilters());

    expect($result->totalLeadValue)->toBe(1000000.0);
});

it('sums order total_amount in each month', function () {
    $user = User::factory()->create();

    Order::factory()->create([
        'created_by' => $user->id,
        'total_amount' => 4000000,
        'order_date' => Carbon::now()->startOfYear()->addDays(3)->format('Y-m-d'),
    ]);
    Order::factory()->create([
        'created_by' => $user->id,
        'total_amount' => 6000000,
        'order_date' => Carbon::now()->startOfYear()->addDays(20)->format('Y-m-d'),
    ]);

    $result = (new TargetReportService)->generate(targetFilters());

    expect($result->totalOrderValue)->toBe(10000000.0);
});

it('excludes opportunities and orders outside the date range', function () {
    $user = User::factory()->create();

    Opportunity::factory()->create([
        'created_by' => $user->id,
        'estimated_revenue' => 900000,
        'created_at' => Carbon::now()->subYears(2),
    ]);
    Order::factory()->create([
        'created_by' => $user->id,
        'total_amount' => 900000,
        'order_date' => Carbon::now()->subYears(2)->format('Y-m-d'),
    ]);

    $result = (new TargetReportService)->generate(targetFilters());

    expect($result->totalLeadValue)->toBe(0.0);
    expect($result->totalOrderValue)->toBe(0.0);
});

it('scopes targets and order value to the selected sales representative', function () {
    $mine = User::factory()->create();
    $other = User::factory()->create();

    Target::create(['user_id' => $mine->id, 'year' => now()->year, 'month' => 1, 'monthly_target' => 1000000]);
    Target::create(['user_id' => $other->id, 'year' => now()->year, 'month' => 1, 'monthly_target' => 9000000]);

    Order::factory()->create([
        'created_by' => $mine->id,
        'total_amount' => 300000,
        'order_date' => Carbon::now()->startOfYear()->addDays(3)->format('Y-m-d'),
    ]);
    Order::factory()->create([
        'created_by' => $other->id,
        'total_amount' => 700000,
        'order_date' => Carbon::now()->startOfYear()->addDays(3)->format('Y-m-d'),
    ]);

    $result = (new TargetReportService)->generate(targetFilters(["userId" => $mine->id]));

    expect($result->totalTarget)->toBe(1000000.0);
    expect($result->totalOrderValue)->toBe(300000.0);
});

it('builds a continuous month axis covering the range', function () {
    $result = (new TargetReportService)->generate(new ReportFilterData(
        startDate: Carbon::parse('2026-03-01'),
        endDate: Carbon::parse('2026-06-30'),
    ));

    expect($result->monthlyComparison)->toHaveCount(4);
    expect($result->monthlyComparison->pluck('period')->toArray())
        ->toBe(['Mar 2026', 'Apr 2026', 'May 2026', 'Jun 2026']);
});

it('survives a cache round trip on the serializing store', function () {
    // The suite runs on the array store, which skips serialization entirely and
    // therefore cannot catch a DTO missing from config/cache.php's
    // serializable_classes allowlist — reads would come back as
    // __PHP_Incomplete_Class on any real deployment.
    config(['cache.default' => 'database']);
    app('cache')->purge('database');

    $user = User::factory()->create();
    Target::create(['user_id' => $user->id, 'year' => now()->year, 'month' => 1, 'monthly_target' => 1234567]);

    $service = app(TargetReportService::class);
    $filters = targetFilters();

    $first = $service->generate($filters);
    $second = $service->generate($filters);

    expect($second)->toBeInstanceOf(\App\DTOs\TargetReportData::class)
        ->and($second->totalTarget)->toBe($first->totalTarget)
        ->and($second->monthlyComparison)->toHaveCount(12);
});
