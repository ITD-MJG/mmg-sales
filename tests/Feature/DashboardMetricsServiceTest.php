<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Order;
use App\Models\Target;
use App\Models\User;
use App\Services\DashboardMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

    $this->metrics = new DashboardMetricsService;
    $this->rep = User::factory()->create(['name' => 'Rep']);
    $this->otherRep = User::factory()->create(['name' => 'Other Rep']);
    $this->importer = User::factory()->create(['name' => 'Importer']);
});

afterEach(function () {
    $this->travelBack();
});

it('attributes revenue to sales_id, not to the user who created the order', function () {
    // Imports are created by one administrator, so created_by credits the
    // importer for every row in the file.
    Order::factory()->create([
        'sales_id' => $this->rep->id,
        'created_by' => $this->importer->id,
        'order_date' => '2026-06-10',
        'total_amount' => 1000,
    ]);

    expect($this->metrics->revenue(now()->startOfMonth(), now()->endOfMonth(), [$this->rep->id]))->toBe(1000.0)
        ->and($this->metrics->revenue(now()->startOfMonth(), now()->endOfMonth(), [$this->importer->id]))->toBe(0.0);
});

it('counts every order when the viewer has global visibility', function () {
    Order::factory()->create(['sales_id' => $this->rep->id, 'order_date' => '2026-06-10', 'total_amount' => 1000]);
    Order::factory()->create(['sales_id' => $this->otherRep->id, 'order_date' => '2026-06-10', 'total_amount' => 250]);

    expect($this->metrics->revenue(now()->startOfMonth(), now()->endOfMonth(), null))->toBe(1250.0)
        ->and($this->metrics->orderCount(now()->startOfMonth(), now()->endOfMonth(), null))->toBe(2);
});

it('leaves revenue outside the requested dates out of the figure', function () {
    Order::factory()->create(['sales_id' => $this->rep->id, 'order_date' => '2026-05-31', 'total_amount' => 900]);
    Order::factory()->create(['sales_id' => $this->rep->id, 'order_date' => '2026-06-01', 'total_amount' => 100]);

    expect($this->metrics->revenue(now()->startOfMonth(), now()->endOfMonth(), [$this->rep->id]))->toBe(100.0);
});

it('decides the win rate from the status change inside the period', function () {
    Lead::factory()->count(3)->create(['created_by' => $this->rep->id, 'status' => 'closed_won', 'updated_at' => '2026-06-05']);
    Lead::factory()->create(['created_by' => $this->rep->id, 'status' => 'closed_lost', 'updated_at' => '2026-06-06']);
    // Decided before the period, so it must not move this month's rate.
    Lead::factory()->create(['created_by' => $this->rep->id, 'status' => 'closed_lost', 'updated_at' => '2026-05-06']);

    $winRate = $this->metrics->winRate(now()->startOfMonth(), now()->endOfMonth(), [$this->rep->id]);

    expect($winRate['won'])->toBe(3)
        ->and($winRate['lost'])->toBe(1)
        ->and($winRate['rate'])->toBe(75.0);
});

it('reports no win rate rather than a division by zero when nothing was decided', function () {
    Lead::factory()->create(['created_by' => $this->rep->id, 'status' => 'prospect']);

    $winRate = $this->metrics->winRate(now()->startOfMonth(), now()->endOfMonth(), [$this->rep->id]);

    expect($winRate['rate'])->toBeNull()
        ->and($winRate['won'])->toBe(0)
        ->and($winRate['lost'])->toBe(0);
});

it('keeps closed leads out of the open pipeline', function () {
    Lead::factory()->create(['created_by' => $this->rep->id, 'status' => 'prospect', 'estimated_revenue' => 500]);
    Lead::factory()->create(['created_by' => $this->rep->id, 'status' => 'closed_won', 'estimated_revenue' => 9000]);
    Lead::factory()->create(['created_by' => $this->rep->id, 'status' => 'closed_lost', 'estimated_revenue' => 4000]);

    expect($this->metrics->openPipeline([$this->rep->id]))->toBe(500.0)
        ->and($this->metrics->openLeadCount([$this->rep->id]))->toBe(1);
});

it('counts a lead for a collaborator it was handed to', function () {
    $lead = Lead::factory()->create([
        'created_by' => $this->otherRep->id,
        'status' => 'prospect',
        'estimated_revenue' => 750,
    ]);

    $lead->collaborators()->attach($this->rep->id, ['added_by' => $this->otherRep->id]);

    expect($this->metrics->openPipeline([$this->rep->id]))->toBe(750.0)
        ->and($this->metrics->openPipeline([$this->importer->id]))->toBe(0.0);
});

it('ranks reps by revenue over the window', function () {
    Order::factory()->create(['sales_id' => $this->rep->id, 'order_date' => '2026-06-10', 'total_amount' => 1000]);
    Order::factory()->create(['sales_id' => $this->rep->id, 'order_date' => '2026-06-11', 'total_amount' => 500]);
    Order::factory()->create(['sales_id' => $this->otherRep->id, 'order_date' => '2026-06-11', 'total_amount' => 2500]);

    $rows = $this->metrics->revenueByRep(now()->subMonths(11)->startOfMonth(), now()->endOfMonth(), null);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['user_id'])->toBe($this->otherRep->id)
        ->and($rows[0]['revenue'])->toBe(2500.0)
        ->and($rows[0]['orders'])->toBe(1)
        ->and($rows[1]['user_id'])->toBe($this->rep->id)
        ->and($rows[1]['revenue'])->toBe(1500.0)
        ->and($rows[1]['orders'])->toBe(2);
});

it('matches target rows on year-month pairs rather than on the year', function () {
    // The window is Jul 2025 - Jun 2026, so a Jan 2026 target is inside it and a
    // Dec 2026 target is not, even though both share a year with the window.
    Target::create(['user_id' => $this->rep->id, 'year' => 2026, 'month' => 1, 'monthly_target' => 1000]);
    Target::create(['user_id' => $this->rep->id, 'year' => 2026, 'month' => 12, 'monthly_target' => 9000]);

    $months = $this->metrics->recentMonths();

    expect($months)->toHaveCount(12)
        ->and($months[0]->format('Y-m'))->toBe('2025-07')
        ->and($months[11]->format('Y-m'))->toBe('2026-06')
        ->and($this->metrics->targetTotalsByUser($months, [$this->rep->id])->get($this->rep->id))->toBe(1000.0);
});

it('gives each month in a window a target, defaulting to zero', function () {
    Target::create(['user_id' => $this->rep->id, 'year' => 2026, 'month' => 1, 'monthly_target' => 1000]);

    $months = $this->metrics->recentMonths();
    $targets = $this->metrics->monthlyTargets($months, $this->rep->id);

    expect($targets)->toHaveCount(12)
        ->and($targets['2026-1'])->toBe(1000.0)
        ->and($targets['2026-6'])->toBe(0.0);
});
