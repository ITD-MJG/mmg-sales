<?php

use App\Models\Activity;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Services\LeadStatusResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('derives new when a lead has no activities', function (): void {
    $lead = Lead::factory()->create(['status' => 'new']);

    expect((new LeadStatusResolver)->derive($lead))->toBe('new');
});

it('treats a missing outcome as no contact under the strict rule but as contact under the lenient one', function (): void {
    $lead = Lead::factory()->create(['status' => 'new']);
    Activity::factory()->forLead($lead)->create(['outcome' => null]);

    $resolver = new LeadStatusResolver;

    expect($resolver->derive($lead))->toBe('new')
        ->and($resolver->deriveFromAnyActivity($lead))->toBe('in_process');
});

it('derives in_process for every outcome that proves the contact was reached', function (string $outcome): void {
    // The factory's outcome default is random and the observer advances the
    // lead on any activity, so pin the status and spell out the outcome.
    $lead = Lead::factory()->create(['status' => 'new']);
    Activity::factory()->forLead($lead)->create(['outcome' => $outcome]);
    $lead->refresh();

    expect((new LeadStatusResolver)->derive($lead))->toBe('in_process');
})->with(['Interested', 'Need more info', 'Postponed']);

it('does not treat no answer as contact', function (): void {
    $lead = Lead::factory()->create(['status' => 'new']);
    Activity::factory()->forLead($lead)->create(['outcome' => 'No Answer']);

    expect((new LeadStatusResolver)->derive($lead))->toBe('new');
});

it('derives closed_lost from a hard stop anywhere in the trail', function (): void {
    $lead = Lead::factory()->create(['status' => 'new']);
    Activity::factory()->forLead($lead)->create(['outcome' => 'Interested']);
    Activity::factory()->forLead($lead)->create(['outcome' => 'Not Interested']);
    Activity::factory()->forLead($lead)->create(['outcome' => 'Interested']);

    expect((new LeadStatusResolver)->derive($lead->refresh()))->toBe('closed_lost');
});

it('does not report drift for a lead with no evidence to contradict its status', function (): void {
    $lead = Lead::factory()->disqualified()->create();

    expect((new LeadStatusResolver)->derive($lead))->toBe('closed_lost');
});

it('reports drift when the stored status disagrees with the activities', function (): void {
    $lead = Lead::factory()->create(['status' => 'new']);
    Activity::factory()->forLead($lead)->create(['outcome' => 'Interested']);
    $lead->refresh();

    // The observer already advanced the lead on that activity, so force the
    // stored value back to prove the resolver reads the activities rather than
    // just echoing the column it compares against.
    $lead->updateQuietly(['status' => 'new']);

    expect((new LeadStatusResolver)->isDrifted($lead))->toBeTrue();
});

it('logs attribute changes on a lead', function (): void {
    $lead = Lead::factory()->create(['status' => 'new']);
    $lead->update(['status' => 'in_process']);

    $entry = DB::table('activity_log')
        ->where('subject_type', Lead::class)
        ->where('subject_id', $lead->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    // Spatie v5 keeps the diff in attribute_changes; `properties` is the
    // caller-supplied bag and stays empty for model events.
    expect($entry)->not->toBeNull()
        ->and($entry->attribute_changes)->toContain('"status"')
        ->and($entry->attribute_changes)->toContain('in_process');
});

it('logs attribute changes on an opportunity', function (): void {
    $opportunity = Opportunity::factory()->create(['stage' => 'new']);
    $opportunity->update(['stage' => 'proposal']);

    $entry = DB::table('activity_log')
        ->where('subject_type', Opportunity::class)
        ->where('subject_id', $opportunity->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry->attribute_changes)->toContain('"stage"')
        ->and($entry->attribute_changes)->toContain('proposal');
});
