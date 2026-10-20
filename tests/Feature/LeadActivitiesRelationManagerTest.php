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
