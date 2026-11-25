<?php

namespace Tests\Feature;

use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Models\Activity;
use App\Models\Department;
use App\Models\Role;
use App\Models\Territory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Role::findOrCreate('Sales Representative');
    Role::findOrCreate('Sales Manager');
    Role::findOrCreate('Area Sales Manager');
    Role::findOrCreate('Marketing Staff');
    Role::findOrCreate('Marketing Manager');
});

test('users always receive both My and My Team tabs', function (): void {
    $salesDepartment = Department::factory()->create(['name' => 'Sales']);
    $user = User::factory()->create();
    $user->assignRole('Sales Representative');
    $user->department_id = $salesDepartment->id;
    $user->save();

    $this->actingAs($user);

    $component = new ListActivities;
    $component->mount();

    expect(array_keys($component->getTabs()))->toBe(['my', 'team']);
});

test('My Activities shows only activities the user created while My Team Activities shows others who assigned the user', function (): void {
    $territory = Territory::factory()->create();
    $manager = User::factory()->create(['territory_id' => $territory->id]);
    $manager->assignRole('Area Sales Manager');
    $manager->department()->associate(Department::factory()->create(['name' => 'Sales']));
    $manager->save();

    $directReport = User::factory()->create([
        'manager_id' => $manager->id,
        'territory_id' => $territory->id,
    ]);
    $peer = User::factory()->create(['territory_id' => $territory->id]);
    $outsider = User::factory()->create();

    $ownActivity = Activity::factory()->create([
        'user_id' => $manager->id,
        'subject' => 'Manager activity',
    ]);
    $assignedActivity = Activity::factory()->create([
        'user_id' => $directReport->id,
        'subject' => 'Direct report assigned me',
    ]);
    $assignedActivity->attendees()->attach($manager);

    $peerAssignedActivity = Activity::factory()->create([
        'user_id' => $peer->id,
        'subject' => 'Peer assigned me',
    ]);
    $peerAssignedActivity->attendees()->attach($manager);

    $unassignedReportActivity = Activity::factory()->create([
        'user_id' => $directReport->id,
        'subject' => 'Report activity without me',
    ]);
    $outsideActivity = Activity::factory()->create([
        'user_id' => $outsider->id,
        'subject' => 'Outside activity',
    ]);

    $component = Livewire::actingAs($manager)->test(ListActivities::class);

    $component->assertSet('activeTab', 'my')
        ->assertCanSeeTableRecords([$ownActivity])
        ->assertCanNotSeeTableRecords([$assignedActivity, $peerAssignedActivity, $unassignedReportActivity, $outsideActivity])
        ->set('activeTab', 'team')
        ->assertCanSeeTableRecords([$assignedActivity, $peerAssignedActivity])
        ->assertCanNotSeeTableRecords([$ownActivity, $unassignedReportActivity, $outsideActivity]);
});

test('outcome remains a filter that can be combined with ownership tabs', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('Sales Manager');
    $report = User::factory()->create(['manager_id' => $manager->id]);

    $ownInterested = Activity::factory()->create([
        'user_id' => $manager->id,
        'outcome' => 'Interested',
    ]);
    $ownNoAnswer = Activity::factory()->create([
        'user_id' => $manager->id,
        'outcome' => 'No Answer',
    ]);
    $teamInterested = Activity::factory()->create([
        'user_id' => $report->id,
        'outcome' => 'Interested',
    ]);
    $teamInterested->attendees()->attach($manager);

    Livewire::actingAs($manager)
        ->test(ListActivities::class)
        ->assertCanSeeTableRecords([$ownInterested, $ownNoAnswer])
        ->filterTable('outcome', 'Interested')
        ->assertCanSeeTableRecords([$ownInterested])
        ->assertCanNotSeeTableRecords([$ownNoAnswer, $teamInterested])
        ->set('activeTab', 'team')
        ->assertCanSeeTableRecords([$teamInterested])
        ->assertCanNotSeeTableRecords([$ownInterested, $ownNoAnswer]);
});

test('a global viewer lands on an unfiltered All Activities tab', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('Super Admin');

    $rep = User::factory()->create();
    $rep->assignRole('Sales Representative');

    $repActivity = Activity::factory()->create([
        'user_id' => $rep->id,
        'subject' => 'Rep visit',
    ]);
    $ownActivity = Activity::factory()->create([
        'user_id' => $superAdmin->id,
        'subject' => 'Admin activity',
    ]);

    $component = Livewire::actingAs($superAdmin)->test(ListActivities::class);

    // A Super Admin has no subordinates, but the ownership tabs still render:
    // "My" is their own log and "My Team" is anything assigned to them.
    expect(array_keys($component->instance()->getTabs()))->toBe(['all', 'my', 'team']);

    $component->assertSet('activeTab', 'all')
        ->assertCanSeeTableRecords([$repActivity, $ownActivity])
        ->set('activeTab', 'my')
        ->assertCanSeeTableRecords([$ownActivity])
        ->assertCanNotSeeTableRecords([$repActivity]);
});
