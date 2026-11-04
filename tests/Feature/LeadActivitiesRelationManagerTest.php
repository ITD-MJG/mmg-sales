<?php

namespace Tests\Feature;

use App\Filament\Resources\Leads\Pages\ViewLead;
use App\Filament\Resources\Leads\RelationManagers\ActivitiesRelationManager;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('Super Admin');
    actingAs($this->user);
});

function leadActivitiesRelationManager(Lead $lead)
{
    return livewire(ActivitiesRelationManager::class, [
        'ownerRecord' => $lead,
        'pageClass' => ViewLead::class,
    ]);
}

it('lists the activities of the lead without an activity code column', function () {
    $lead = Lead::factory()->create();
    $activities = Activity::factory()->count(2)->forLead($lead)->create();

    leadActivitiesRelationManager($lead)
        ->assertCanSeeTableRecords($activities)
        ->assertTableColumnDoesNotExist('activity_code')
        ->assertTableColumnExists('performed_at');
});

it('shows the activity detail infolist in the view modal', function () {
    $lead = Lead::factory()->create();
    $activity = Activity::factory()->forLead($lead)->create([
        'subject' => 'Quarterly review meeting',
        'location' => 'Jakarta Head Office',
        'stakeholder_feedback' => 'Wants a follow-up demo next month',
    ]);

    leadActivitiesRelationManager($lead)
        ->mountTableAction('view', $activity)
        ->assertSchemaComponentStateSet('subject', 'Quarterly review meeting')
        ->assertSchemaComponentStateSet('location', 'Jakarta Head Office')
        ->assertSchemaComponentStateSet('stakeholder_feedback', 'Wants a follow-up demo next month');
});

it('shows schedule and meeting note columns instead of the customer column', function () {
    $lead = Lead::factory()->create();
    $activity = Activity::factory()->forLead($lead)->create();

    leadActivitiesRelationManager($lead)
        ->assertCanSeeTableRecords([$activity])
        ->assertTableColumnDoesNotExist('customer.name')
        ->assertTableColumnExists('visit_started_at')
        ->assertTableColumnExists('visit_ended_at')
        ->assertTableColumnExists('purpose')
        ->assertTableColumnExists('expectations')
        ->assertTableColumnExists('targets');
});

it('shows only the time on the start and end columns', function () {
    $lead = Lead::factory()->create();
    $activity = Activity::factory()->forLead($lead)->create([
        'visit_started_at' => '2026-11-04 09:15:00',
        'visit_ended_at' => '2026-11-04 11:45:00',
    ]);

    // The date lives on the Date column; Start/End are a time-of-day pair, so
    // repeating the date on both would be noise.
    leadActivitiesRelationManager($lead)
        ->assertCanSeeTableRecords([$activity])
        ->assertTableColumnFormattedStateSet('visit_started_at', '09:15', $activity)
        ->assertTableColumnFormattedStateSet('visit_ended_at', '11:45', $activity);
});
