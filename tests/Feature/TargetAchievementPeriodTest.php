<?php

use App\DTOs\ReportFilterData;
use App\Services\Reports\TargetAchievementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeTargets(int $userId, int $year, array $months): void
{
    foreach ($months as $month => $amount) {
        App\Models\Target::create([
            'user_id' => $userId,
            'year' => $year,
            'month' => $month,
            'monthly_target' => $amount,
        ]);
    }
}

function annualTargetFor(int $userId, string $start, string $end): float
{
    $filters = ReportFilterData::fromArray([
        'start_date' => $start,
        'end_date' => $end,
        'user_id' => $userId,
    ]);

    return app(TargetAchievementService::class)->generate($filters)->annualTarget;
}

it('sums every month of the year for a full-year range', function () {
    $user = App\Models\User::factory()->create();
    makeTargets($user->id, 2026, [1 => 100, 7 => 200, 12 => 300]);

    expect(annualTargetFor($user->id, '2026-01-01', '2026-12-31'))->toBe(600.0);
});

it('sums only the months inside the selected range', function () {
    $user = App\Models\User::factory()->create();
    makeTargets($user->id, 2026, [1 => 100, 7 => 200, 8 => 400, 9 => 800, 12 => 300]);

    expect(annualTargetFor($user->id, '2026-07-01', '2026-09-30'))->toBe(1400.0);
});

it('excludes months before the range start within the start year', function () {
    $user = App\Models\User::factory()->create();
    makeTargets($user->id, 2026, [3 => 100, 6 => 200, 9 => 400]);

    expect(annualTargetFor($user->id, '2026-06-01', '2026-12-31'))->toBe(600.0);
});

it('includes whole intervening years and the boundary months of a cross-year range', function () {
    $user = App\Models\User::factory()->create();
    makeTargets($user->id, 2025, [10 => 50, 11 => 70, 12 => 90]);
    makeTargets($user->id, 2026, [1 => 20, 2 => 30, 3 => 40]);
    makeTargets($user->id, 2027, [1 => 999]);

    // Nov 2025 through Feb 2026: 70 + 90 + 20 + 30, excluding Oct 2025 and all of 2027.
    expect(annualTargetFor($user->id, '2025-11-01', '2026-02-28'))->toBe(210.0);
});

it('includes every month of a fully spanned middle year', function () {
    $user = App\Models\User::factory()->create();
    makeTargets($user->id, 2025, [12 => 10]);
    makeTargets($user->id, 2026, [1 => 100, 6 => 500, 12 => 900]);
    makeTargets($user->id, 2027, [1 => 20]);

    // Dec 2025 through Jan 2027: all of 2026 counts.
    expect(annualTargetFor($user->id, '2025-12-01', '2027-01-31'))->toBe(1530.0);
});
