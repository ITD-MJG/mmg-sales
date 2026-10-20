<?php

use App\Models\Activity;
use App\Models\ActivityComment;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Department;
use App\Models\Territory;
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

it('lets an opportunity collaborator comment', function () {
    $creator = User::factory()->create();
    $collaborator = permittedUser();
    $opportunity = Opportunity::factory()->create(['created_by' => $creator->id]);
    $opportunity->collaborators()->attach($collaborator->id, ['added_by' => $creator->id]);
    $activity = Activity::factory()->create(['lead_id' => null, 'opportunity_id' => $opportunity->id]);

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
it('lets an area sales manager comment on a rep activity in their territory', function () {
    $territory = Territory::factory()->create();

    $rep = User::factory()->create(['territory_id' => $territory->id]);
    $activity = Activity::factory()->create(['user_id' => $rep->id]);

    $manager = User::factory()->create(['territory_id' => $territory->id]);
    $manager->assignRole('Area Sales Manager');

    expect(canComment($manager, $activity))->toBeTrue();
});

it('denies an area sales manager commenting outside their territory', function () {
    $territory = Territory::factory()->create();
    $other = Territory::factory()->create();

    $rep = User::factory()->create(['territory_id' => $other->id]);
    $activity = Activity::factory()->create(['user_id' => $rep->id]);

    $manager = User::factory()->create(['territory_id' => $territory->id]);
    $manager->assignRole('Area Sales Manager');

    expect(canComment($manager, $activity))->toBeFalse();
});

it('denies an area sales manager without a territory', function () {
    $territory = Territory::factory()->create();

    $rep = User::factory()->create(['territory_id' => $territory->id]);
    $activity = Activity::factory()->create(['user_id' => $rep->id]);

    $manager = User::factory()->create(['territory_id' => null]);
    $manager->assignRole('Area Sales Manager');

    expect(canComment($manager, $activity))->toBeFalse();
});

it('denies a territory-matching user in a non-manager role', function () {
    $territory = Territory::factory()->create();

    $rep = User::factory()->create(['territory_id' => $territory->id]);
    $activity = Activity::factory()->create(['user_id' => $rep->id]);

    $peer = User::factory()->create(['territory_id' => $territory->id]);
    $peer->assignRole('Sales Staff');

    expect(canComment($peer, $activity))->toBeFalse();
});

it('lets the management director comment on any activity', function () {
    $dept = Department::factory()->create(['name' => 'Management']);
    $director = User::factory()->create(['department_id' => $dept->id]);
    $director->assignRole('Management Director');
    $director->givePermissionTo('create_activity_comment');

    $activity = Activity::factory()->create();

    expect(canComment($director, $activity))->toBeTrue();
});

it('still denies the management director editing someone elses comment', function () {
    $dept = Department::factory()->create(['name' => 'Management']);
    $author = User::factory()->create();
    $comment = ActivityComment::factory()->create(['user_id' => $author->id]);

    $director = User::factory()->create(['department_id' => $dept->id]);
    $director->assignRole('Management Director');

    expect($director->can('update', $comment))->toBeFalse();
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
