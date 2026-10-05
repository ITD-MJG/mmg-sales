<?php

use App\Filament\Pages\UserHierarchy;
use App\Models\Position;
use App\Models\Territory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);
});

/** A user with all three hierarchy fields set, so the modal has something to prefill. */
function userWithHierarchy(): array
{
    $position = Position::factory()->create(['name' => 'Area Sales Manager']);
    $territory = Territory::factory()->create(['name' => 'Barat']);
    $manager = User::factory()->create(['name' => 'R. Hapsarendra']);

    $user = User::factory()->create([
        'position_id' => $position->id,
        'territory_id' => $territory->id,
        'manager_id' => $manager->id,
    ]);

    return [$user, $position, $territory, $manager];
}

it('prefills position, territory and manager when the edit modal opens', function () {
    [$user, $position, $territory, $manager] = userWithHierarchy();

    Livewire::test(UserHierarchy::class)
        ->mountTableAction('editHierarchy', $user)
        ->assertTableActionDataSet([
            'position_id' => $position->id,
            'territory_id' => $territory->id,
            'manager_id' => $manager->id,
        ]);
});

it('prefills nulls for a user with no hierarchy set', function () {
    $user = User::factory()->create([
        'position_id' => null,
        'territory_id' => null,
        'manager_id' => null,
    ]);

    Livewire::test(UserHierarchy::class)
        ->mountTableAction('editHierarchy', $user)
        ->assertTableActionDataSet([
            'position_id' => null,
            'territory_id' => null,
            'manager_id' => null,
        ]);
});

it('fills the modal from the row being edited, not a previously opened row', function () {
    [$first, $firstPosition] = userWithHierarchy();
    [$second, $secondPosition] = userWithHierarchy();

    expect($firstPosition->id)->not->toBe($secondPosition->id);

    Livewire::test(UserHierarchy::class)
        ->mountTableAction('editHierarchy', $first)
        ->assertTableActionDataSet(['position_id' => $firstPosition->id])
        ->unmountTableAction()
        ->mountTableAction('editHierarchy', $second)
        ->assertTableActionDataSet(['position_id' => $secondPosition->id]);
});

it('still saves the submitted hierarchy values', function () {
    [$user] = userWithHierarchy();

    $newPosition = Position::factory()->create(['name' => 'Regional Sales Manager']);
    $newTerritory = Territory::factory()->create(['name' => 'Timur']);
    $newManager = User::factory()->create(['name' => 'Donny Seprana']);

    Livewire::test(UserHierarchy::class)
        ->mountTableAction('editHierarchy', $user)
        ->setTableActionData([
            'position_id' => $newPosition->id,
            'territory_id' => $newTerritory->id,
            'manager_id' => $newManager->id,
        ])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($user->fresh())
        ->position_id->toBe($newPosition->id)
        ->territory_id->toBe($newTerritory->id)
        ->manager_id->toBe($newManager->id);
});

it('saves the prefilled values unchanged when the modal is submitted as-is', function () {
    [$user, $position, $territory, $manager] = userWithHierarchy();

    Livewire::test(UserHierarchy::class)
        ->mountTableAction('editHierarchy', $user)
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($user->fresh())
        ->position_id->toBe($position->id)
        ->territory_id->toBe($territory->id)
        ->manager_id->toBe($manager->id);
});
