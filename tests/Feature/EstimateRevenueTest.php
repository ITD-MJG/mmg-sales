<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\EstimateRevenue;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('totals expected revenue across the current and following eleven closing months', function () {
    $this->travelTo(now()->setDate(2026, 10, 15)->startOfDay());
    $user = User::factory()->create();

    // The widget is scoped to the viewer, so the leads must be attributed to them.
    Lead::factory()->create(['created_by' => $user->id, 'expected_closing_date' => '2026-10-05', 'estimated_revenue' => 1250]);
    Lead::factory()->create(['created_by' => $user->id, 'expected_closing_date' => '2026-10-31', 'estimated_revenue' => 2750]);
    Lead::factory()->create(['created_by' => $user->id, 'expected_closing_date' => '2027-09-01', 'estimated_revenue' => 500]);
    Lead::factory()->create(['created_by' => $user->id, 'expected_closing_date' => '2027-10-01', 'estimated_revenue' => 9000]);
    Lead::factory()->create(['created_by' => $user->id, 'expected_closing_date' => null, 'estimated_revenue' => 4000]);

    $this->actingAs($user);
    $data = (function (): array {
        return $this->getData();
    })->call(app(EstimateRevenue::class));

    expect($data['labels'])->toHaveCount(12)
        ->and($data['labels'][0])->toBe('Oct 2026')
        ->and($data['labels'][11])->toBe('Sep 2027')
        ->and($data['datasets'][0]['data'][0])->toBe(4000.0)
        ->and($data['datasets'][0]['data'][11])->toBe(500.0)
        ->and(array_sum($data['datasets'][0]['data']))->toBe(4500.0);
});

it('declares a bar chart with coloured datasets and registers on the dashboard', function () {
    $data = (function (): array {
        return $this->getData();
    })->call(app(EstimateRevenue::class));

    $type = (function (): string {
        return $this->getType();
    })->call(app(EstimateRevenue::class));

    // Revenue and target are drawn as two differently coloured datasets.
    expect($data['datasets'])->toHaveCount(2)
        ->and($data['datasets'][0]['backgroundColor'])->toBe('rgba(37, 99, 235, 0.75)')
        ->and($data['datasets'][1]['backgroundColor'])->toBe('rgba(220, 38, 38, 0.65)')
        ->and($type)->toBe('bar')
        ->and((new Dashboard)->getWidgets())->toContain(EstimateRevenue::class);
});

it('renders with no leads', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(EstimateRevenue::class)->assertOk();
});
