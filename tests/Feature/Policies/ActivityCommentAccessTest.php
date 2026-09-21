<?php

use App\Models\Activity;
use App\Models\ActivityComment;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
});

/** A user holding the Sales Staff role, which grants create_activity_comment. */
function permittedUser(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole('Sales Staff'));
}

function canComment(User $user, Activity $activity): bool
{
    return Gate::forUser($user)->allows('createForActivity', [ActivityComment::class, $activity]);
}

it('lets the activity owner comment', function () {
    $rep = permittedUser();
    $activity = Activity::factory()->create(['user_id' => $rep->id]);

    expect(canComment($rep, $activity))->toBeTrue();
});

it('lets a listed attendee comment', function () {
    $rep = permittedUser();
    $activity = Activity::factory()->create();
    $activity->attendees()->attach($rep->id);

    expect(canComment($rep, $activity))->toBeTrue();
});

it('lets the lead creator comment', function () {
    $creator = permittedUser();
    $lead = Lead::factory()->create(['created_by' => $creator->id]);
    $activity = Activity::factory()->create(['lead_id' => $lead->id]);

    expect(canComment($creator, $activity))->toBeTrue();
});

it('lets a lead collaborator comment', function () {
    $creator = User::factory()->create();
    $collaborator = permittedUser();
    $lead = Lead::factory()->create(['created_by' => $creator->id]);
    $lead->collaborators()->attach($collaborator->id, ['added_by' => $creator->id]);
    $activity = Activity::factory()->create(['lead_id' => $lead->id]);

    expect(canComment($collaborator, $activity))->toBeTrue();
});

it('denies a permitted user who is not attached to the activity', function () {
    $stranger = permittedUser();
    $activity = Activity::factory()->create();

    expect(canComment($stranger, $activity))->toBeFalse();
});

it('denies an attached user without the create permission', function () {
    $rep = User::factory()->create();
    $activity = Activity::factory()->create(['user_id' => $rep->id]);

    expect(canComment($rep, $activity))->toBeFalse();
});

it('lets a super admin comment on any activity', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $activity = Activity::factory()->create();

    expect(canComment($admin, $activity))->toBeTrue();
});

it('scopes activities to the ones a user belongs to', function () {
    $rep = permittedUser();

    $own = Activity::factory()->create(['user_id' => $rep->id]);
    $attending = Activity::factory()->create();
    $attending->attendees()->attach($rep->id);

    $lead = Lead::factory()->create(['created_by' => $rep->id]);
    $viaLead = Activity::factory()->create(['lead_id' => $lead->id]);

    $unrelated = Activity::factory()->create();

    $visible = Activity::query()->accessibleBy($rep)->pluck('id');

    expect($visible)->toContain($own->id, $attending->id, $viaLead->id)
        ->not->toContain($unrelated->id);
});
