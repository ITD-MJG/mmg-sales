<?php

use App\Models\Activity;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The command rewrites statuses from LeadStatusResolver, so these tests assert
 * the write-back path rather than re-testing the derivation rules themselves.
 */
it('promotes leads whose activities prove the contact was reached', function (): void {
    $lead = Lead::factory()->create(['status' => 'new']);
    Activity::factory()->forLead($lead)->create(['outcome' => 'Interested']);

    $this->artisan('leads:sync-statuses')->assertSuccessful();

    expect($lead->fresh()->status)->toBe('in_process');
});

it('demotes a contacted lead with nothing to support it', function (): void {
    $lead = Lead::factory()->create(['status' => 'in_process']);
    Activity::factory()->forLead($lead)->create(['outcome' => 'No Answer']);

    $this->artisan('leads:sync-statuses')->assertSuccessful();

    expect($lead->fresh()->status)->toBe('new');
});

it('never touches a disqualified lead', function (): void {
    $lead = Lead::factory()->disqualified()->create();
    Activity::factory()->forLead($lead)->create(['outcome' => 'Interested']);

    $this->artisan('leads:sync-statuses')->assertSuccessful();

    expect($lead->fresh()->status)->toBe('closed_lost');
});

it('changes nothing on a dry run', function (): void {
    $lead = Lead::factory()->create(['status' => 'new']);
    Activity::factory()->forLead($lead)->create(['outcome' => 'Interested']);

    // The observer already advanced the lead on that activity; put it back so
    // there is a real difference for the dry run to find. Written through the
    // query builder so no model event can advance it again.
    DB::table('leads')->where('id', $lead->id)->update(['status' => 'new']);

    $this->artisan('leads:sync-statuses', ['--dry-run' => true])
        ->expectsOutputToContain('would change')
        ->assertSuccessful();

    expect($lead->fresh()->status)->toBe('new');
});

it('leaves a lead with no activities alone', function (): void {
    $lead = Lead::factory()->create(['status' => 'in_process']);

    $this->artisan('leads:sync-statuses')->assertSuccessful();

    expect($lead->fresh()->status)->toBe('in_process');
});
