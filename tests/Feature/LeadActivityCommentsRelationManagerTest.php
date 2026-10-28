<?php

namespace Tests\Feature;

use App\Filament\Resources\Leads\Pages\ViewLead;
use App\Filament\Resources\Leads\RelationManagers\ActivityCommentsRelationManager;
use App\Models\Activity;
use App\Models\ActivityComment;
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
    \App\Models\Role::findOrCreate('Sales Representative');
});

function leadRelationManager(Lead $lead)
{
    return livewire(ActivityCommentsRelationManager::class, [
        'ownerRecord' => $lead,
        'pageClass' => ViewLead::class,
    ]);
}

it('lists only the comments of activities belonging to the lead', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Admin');
    actingAs($user);

    $lead = Lead::factory()->create();
    $activity = Activity::factory()->forLead($lead)->create();
    $comment = ActivityComment::factory()->create(['activity_id' => $activity->id]);

    $otherComment = ActivityComment::factory()->create();

    leadRelationManager($lead)
        ->assertCanSeeTableRecords([$comment])
        ->assertCanNotSeeTableRecords([$otherComment]);
});

it('lets the lead creator add a comment on their own activity', function () {
    $creator = User::factory()->create();
    $creator->assignRole('Sales Staff');
    actingAs($creator);

    $lead = Lead::factory()->create(['created_by' => $creator->id]);
    $activity = Activity::factory()->forLead($lead)->create(['user_id' => $creator->id]);

    leadRelationManager($lead)
        ->callTableAction('create', data: [
            'activity_id' => $activity->id,
            'comment' => 'Followed up by phone, waiting for PO.',
        ])
        ->assertHasNoActionErrors();

    expect(ActivityComment::query()->where('activity_id', $activity->id)->sole())
        ->comment->toBe('Followed up by phone, waiting for PO.')
        ->user_id->toBe($creator->id);
});

it('denies creating a comment for a user without the activity comment permission', function () {
    $outsider = User::factory()->create();
    actingAs($outsider);

    $lead = Lead::factory()->create(['created_by' => $outsider->id]);
    $activity = Activity::factory()->forLead($lead)->create();

    leadRelationManager($lead)
        ->assertTableActionHidden('create');

    expect(ActivityComment::query()->count())->toBe(0);
});
