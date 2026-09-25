<?php

use App\Models\Activity;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates an OPP prefixed code', function () {
    $opportunity = Opportunity::factory()->create();

    expect($opportunity->opportunity_code)->toMatch('/^OPP-\d{6}-\d{4}$/');
});

it('exposes all seven stages', function () {
    foreach (['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost'] as $stage) {
        $opportunity = Opportunity::factory()->create(['stage' => $stage]);
        expect($opportunity->fresh()->stage)->toBe($stage);
    }
});

it('links back to its source lead', function () {
    $lead = Lead::factory()->create();
    $opportunity = Opportunity::factory()->create(['converted_from_lead_id' => $lead->id]);

    expect($opportunity->sourceLead->id)->toBe($lead->id);
});

it('relates activities through opportunity_id', function () {
    $opportunity = Opportunity::factory()->create();
    Activity::factory()->create(['lead_id' => null, 'opportunity_id' => $opportunity->id]);

    expect($opportunity->activities()->count())->toBe(1);
});

it('scopes visibility by creator and collaborator', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $mine = Opportunity::factory()->create(['created_by' => $owner->id]);
    Opportunity::factory()->create(['created_by' => $stranger->id]);

    $visible = Opportunity::accessibleBy($owner)->pluck('id');

    expect($visible)->toContain($mine->id)->toHaveCount(1);
});

it('computes aging in days', function () {
    $opportunity = Opportunity::factory()->create(['created_at' => now()->subDays(4)]);

    expect($opportunity->aging)->toBe('4 days');
});
