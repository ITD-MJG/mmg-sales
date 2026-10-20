<?php

use App\DTOs\ReportFilterData;
use App\Models\Opportunity;
use App\Models\Order;
use App\Models\Target;
use App\Models\User;
use App\Services\Reports\TargetAchievementService;
use Carbon\Carbon;

function targetFilters(User $user, array $overrides = []): ReportFilterData
{
    return new ReportFilterData(...array_merge([
        'startDate' => Carbon::now()->startOfMonth(),
        'endDate' => Carbon::now()->endOfMonth(),
        'userId' => $user->id,
    ], $overrides));
}

it('returns zeroed data when no user is selected', function () {
    $service = new TargetAchievementService;
    $result = $service->generate(new ReportFilterData(
        startDate: Carbon::now()->startOfMonth(),
        endDate: Carbon::now()->endOfMonth(),
    ));

    expect($result->monthlyTarget)->toBe(0.0);
    expect($result->orderAchievement)->toBe(0.0);
    expect($result->monthlyBreakdown)->toBeEmpty();
});

it('compares orders against the monthly target', function () {
    $user = User::factory()->create();

    Target::create([
        'user_id' => $user->id,
        'year' => Carbon::now()->year,
        'month' => Carbon::now()->month,
        'annual_target' => 1200000,
        'monthly_target' => 100000,
    ]);

    Order::factory()->create([
        'created_by' => $user->id,
        'order_date' => Carbon::now()->format('Y-m-d'),
        'total_amount' => 50000,
    ]);

    $result = (new TargetAchievementService)->generate(targetFilters($user));

    expect($result->monthlyTarget)->toBe(100000.0);
    expect($result->totalOrderRevenue)->toBe(50000.0);
    expect($result->orderAchievement)->toBe(50.0);
});

it('compares lead pipeline value against the monthly target', function () {
    $user = User::factory()->create();

    Target::create([
        'user_id' => $user->id,
        'year' => Carbon::now()->year,
        'month' => Carbon::now()->month,
        'monthly_target' => 200000,
    ]);

    Opportunity::factory()->create([
        'created_by' => $user->id,
        'estimated_revenue' => 80000,
    ]);

    $result = (new TargetAchievementService)->generate(targetFilters($user));

    expect($result->totalLeadValue)->toBe(80000.0);
    expect($result->leadAchievement)->toBe(40.0);
});

it('only counts records belonging to the selected user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Target::create([
        'user_id' => $user->id,
        'year' => Carbon::now()->year,
        'month' => Carbon::now()->month,
        'monthly_target' => 100000,
    ]);

    Order::factory()->create([
        'created_by' => $user->id,
        'order_date' => Carbon::now()->format('Y-m-d'),
        'total_amount' => 10000,
    ]);
    Order::factory()->create([
        'created_by' => $other->id,
        'order_date' => Carbon::now()->format('Y-m-d'),
        'total_amount' => 999999,
    ]);

    $result = (new TargetAchievementService)->generate(targetFilters($user));

    expect($result->totalOrderRevenue)->toBe(10000.0);
    expect($result->totalOrders)->toBe(1);
});

it('excludes orders outside the date range', function () {
    $user = User::factory()->create();

    Order::factory()->create([
        'created_by' => $user->id,
        'order_date' => Carbon::now()->format('Y-m-d'),
        'total_amount' => 10000,
    ]);
    Order::factory()->create([
        'created_by' => $user->id,
        'order_date' => Carbon::now()->subMonths(3)->format('Y-m-d'),
        'total_amount' => 50000,
    ]);

    $result = (new TargetAchievementService)->generate(targetFilters($user));

    expect($result->totalOrderRevenue)->toBe(10000.0);
});

it('builds a monthly breakdown covering the whole range', function () {
    $user = User::factory()->create();

    Target::create([
        'user_id' => $user->id,
        'year' => Carbon::now()->year,
        'month' => Carbon::now()->month,
        'monthly_target' => 100000,
    ]);

    Order::factory()->create([
        'created_by' => $user->id,
        'order_date' => Carbon::now()->format('Y-m-d'),
        'total_amount' => 25000,
    ]);

    Opportunity::factory()->create([
        'created_by' => $user->id,
        'estimated_revenue' => 40000,
    ]);

    $result = (new TargetAchievementService)->generate(targetFilters(
        $user,
        ['endDate' => Carbon::now()->addMonths(2)->endOfMonth()],
    ));

    expect($result->monthlyBreakdown)->toHaveCount(3);

    $current = $result->monthlyBreakdown->first();
    expect($current['target'])->toBe(100000.0);
    expect($current['orders'])->toBe(25000.0);
    expect($current['leads'])->toBe(40000.0);
    expect($current['order_achievement'])->toBe(25.0);
    expect($current['lead_achievement'])->toBe(40.0);
});
