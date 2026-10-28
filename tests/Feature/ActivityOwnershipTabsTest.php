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

test('users without subordinates only receive the My Activities tab', function (): void {
    $salesDepartment = Department::factory()->create(['name' => 'Sales']);
    $user = User::factory()->create();
    $user->assignRole('Sales Representative');
    $user->department_id = $salesDepartment->id;
    $user->save();

    $component = new ListActivities;
    $component->mount();

    expect(array_keys($component->getTabs()))->toBe(['my']);
});

test('My Team Activities includes direct and indirect reports and the territory while My Activities stays own-only', function (): void {
    $territory = Territory::factory()->create();
    $manager = User::factory()->create(['territory_id' => $territory->id]);
    $manager->assignRole('Area Sales Manager');
    $manager->department()->associate(Department::factory()->create(['name' => 'Sales']));
    $manager->save();

    $directReport = User::factory()->create([
        'manager_id' => $manager->id,
        'territory_id' => $territory->id,
    ]);
    $indirectReport = User::factory()->create([
        'manager_id' => $directReport->id,
        'territory_id' => $territory->id,
    ]);
    $territoryPeer = User::factory()->create(['territory_id' => $territory->id]);
    $outsider = User::factory()->create();

    $ownActivity = Activity::factory()->create([
        'user_id' => $manager->id,
        'subject' => 'Manager activity',
    ]);
    $directActivity = Activity::factory()->create([
        'user_id' => $directReport->id,
        'subject' => 'Direct report activity',
    ]);
    $indirectActivity = Activity::factory()->create([
        'user_id' => $indirectReport->id,
        'subject' => 'Indirect report activity',
    ]);
    $territoryActivity = Activity::factory()->create([
        'user_id' => $territoryPeer->id,
        'subject' => 'Territory activity',
    ]);
    $outsideActivity = Activity::factory()->create([
        'user_id' => $outsider->id,
        'subject' => 'Outside activity',
    ]);

    $component = Livewire::actingAs($manager)->test(ListActivities::class);

    $component->assertSet('activeTab', 'my')
        ->assertCanSeeTableRecords([$ownActivity])
        ->assertCanNotSeeTableRecords([$directActivity, $indirectActivity, $territoryActivity, $outsideActivity])
        ->set('activeTab', 'team')
        ->assertCanSeeTableRecords([$directActivity, $indirectActivity, $territoryActivity])
        ->assertCanNotSeeTableRecords([$ownActivity, $outsideActivity]);
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
