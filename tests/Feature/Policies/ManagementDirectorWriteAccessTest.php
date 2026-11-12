<?php

use App\Models\Activity;
use App\Models\Department;
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

/**
 * A Director in the Management department: global viewer that may create and
 * edit Leads and Activities, but never delete them.
 */
function directorWriter(): User
{
    $management = Department::factory()->create(['name' => Department::MANAGEMENT]);
    $director = User::factory()->create(['department_id' => $management->id]);
    $director->assignRole('Management Director');

    return $director;
}

function leadOwner(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole('Sales Staff'));
}

it('grants a Management director the lead and activity write permissions', function () {
    $director = directorWriter();

    expect($director->hasPermissionTo('create_lead'))->toBeTrue()
        ->and($director->hasPermissionTo('update_lead'))->toBeTrue()
        ->and($director->hasPermissionTo('view_any_activity'))->toBeTrue()
        ->and($director->hasPermissionTo('create_activity'))->toBeTrue()
        ->and($director->hasPermissionTo('update_activity'))->toBeTrue()
        ->and($director->hasPermissionTo('delete_lead'))->toBeFalse()
        ->and($director->hasPermissionTo('delete_activity'))->toBeFalse();
});

it('lets a Management director create a lead', function () {
    $director = directorWriter();

    expect(Gate::forUser($director)->allows('create', Lead::class))->toBeTrue();
});

it('lets a Management director edit a lead created by someone else', function () {
    $director = directorWriter();
    $lead = Lead::factory()->create(['created_by' => leadOwner()->id]);

    expect(Gate::forUser($director)->allows('update', $lead))->toBeTrue();
});

it('keeps a Management director from deleting a lead', function () {
    $director = directorWriter();
    $lead = Lead::factory()->create(['created_by' => leadOwner()->id]);

    expect(Gate::forUser($director)->allows('delete', $lead))->toBeFalse();
});

it('lets a Management director log an activity against any lead', function () {
    $director = directorWriter();
    $lead = Lead::factory()->create(['created_by' => leadOwner()->id]);

    expect(Gate::forUser($director)->allows('createForLead', [Activity::class, $lead]))->toBeTrue();
});

it('lets a Management director edit an activity owned by someone else', function () {
    $director = directorWriter();
    $activity = Activity::factory()->create(['user_id' => leadOwner()->id]);

    expect(Gate::forUser($director)->allows('update', $activity))->toBeTrue();
});

it('keeps a Management director from deleting an activity', function () {
    $director = directorWriter();
    $activity = Activity::factory()->create(['user_id' => leadOwner()->id]);

    expect(Gate::forUser($director)->allows('delete', $activity))->toBeFalse();
});
