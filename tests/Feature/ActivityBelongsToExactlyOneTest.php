<?php

use App\Models\Activity;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Neutral activity attributes so the legacy ActivityObserver cannot push the thin
 * lead into a status the new enum does not allow. The observer itself is Task 10's scope.
 */
function leadActivity(Lead $lead, array $attributes = []): Activity
{
    return Activity::factory()->create(array_merge([
        'lead_id' => $lead->id,
        'opportunity_id' => null,
        'type' => 'call',
        'subject' => 'Follow up call',
        'outcome' => 'Interested',
        'performed_at' => now(),
    ], $attributes));
}

it('accepts an activity on a lead only', function () {
    $activity = leadActivity(Lead::factory()->create(['status' => 'contacted']));

    expect($activity->exists)->toBeTrue();
});

it('accepts an activity on an opportunity only', function () {
    $activity = Activity::factory()->create([
        'lead_id' => null,
        'opportunity_id' => Opportunity::factory()->create()->id,
    ]);

    expect($activity->exists)->toBeTrue();
});

it('rejects an activity on both', function () {
    expect(fn () => Activity::factory()->create([
        'lead_id' => Lead::factory()->create()->id,
        'opportunity_id' => Opportunity::factory()->create()->id,
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects an activity on neither', function () {
    expect(fn () => Activity::factory()->create([
        'lead_id' => null,
        'opportunity_id' => null,
    ]))->toThrow(InvalidArgumentException::class);
});

it('has the opportunity_id column', function () {
    expect(Schema::hasColumn('activities', 'opportunity_id'))->toBeTrue();
});

it('resolves the opportunity relation', function () {
    $opportunity = Opportunity::factory()->create();

    $activity = Activity::factory()->create([
        'lead_id' => null,
        'opportunity_id' => $opportunity->id,
    ]);

    expect($activity->opportunity->is($opportunity))->toBeTrue();
});

it('runs the observer when an activity is created', function () {
    $lead = Lead::factory()->create(['status' => 'new']);

    leadActivity($lead);

    expect($lead->fresh()->status)->toBe('contacted');
});

it('grants access to the creator of the linked lead', function () {
    $creator = User::factory()->create();
    $lead = Lead::factory()->create(['created_by' => $creator->id, 'status' => 'contacted']);
    $activity = leadActivity($lead);

    expect($activity->isAccessibleBy($creator))->toBeTrue();
});

it('grants access to the creator of the linked opportunity', function () {
    $creator = User::factory()->create();
    $opportunity = Opportunity::factory()->create(['created_by' => $creator->id]);
    $activity = Activity::factory()->create(['lead_id' => null, 'opportunity_id' => $opportunity->id]);

    expect($activity->isAccessibleBy($creator))->toBeTrue();
});

it('denies access to a stranger', function () {
    $stranger = User::factory()->create();
    $lead = Lead::factory()->create(['status' => 'contacted']);
    $activity = leadActivity($lead);

    expect($activity->isAccessibleBy($stranger))->toBeFalse();
});

it('scopes activities to both the lead and opportunity branches', function () {
    $rep = User::factory()->create();

    $lead = Lead::factory()->create(['created_by' => $rep->id, 'status' => 'contacted']);
    $viaLead = leadActivity($lead);

    $opportunity = Opportunity::factory()->create(['created_by' => $rep->id]);
    $viaOpportunity = Activity::factory()->forOpportunity($opportunity)->create();

    $unrelated = Activity::factory()->forOpportunity(Opportunity::factory()->create())->create();

    $visible = Activity::query()->accessibleBy($rep)->pluck('id');

    expect($visible)->toContain($viaLead->id, $viaOpportunity->id)
        ->not->toContain($unrelated->id);
});
