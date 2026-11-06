<?php

use App\Models\Activity;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates a LEAD prefixed code', function () {
    expect(Lead::factory()->create()->lead_code)->toMatch('/^LEAD-\d{6}-\d{4}$/');
});

it('accepts every status in the pipeline enum', function () {
    foreach (array_keys(Lead::STATUS_LABELS) as $status) {
        expect(Lead::factory()->create(['status' => $status])->fresh()->status)->toBe($status);
    }
});

it('keeps opportunity-only deal fields off the lead', function () {
    $lead = Lead::factory()->create();

    // estimated_revenue and confidence_level moved onto the lead deliberately
    // (see 2026_10_21_000001 and 2026_11_06_000001); the opportunity keeps its
    // own copies, and these two stay opportunity-only.
    expect($lead->getFillable())->not->toContain('estimated_value')
        ->and($lead->getFillable())->not->toContain('stage');
});

it('relates activities through lead_id', function () {
    $lead = Lead::factory()->create();
    Activity::factory()->create(['lead_id' => $lead->id, 'opportunity_id' => null]);

    expect($lead->activities()->count())->toBe(1);
});
