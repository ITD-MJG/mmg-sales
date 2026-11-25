<?php

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Department;
use App\Models\Principal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\seed;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

it('attaches principals to a user and exposes cached ids', function () {
    $user = User::factory()->create();
    $first = Principal::factory()->create();
    $second = Principal::factory()->create();

    $user->principals()->attach([$first->id, $second->id]);

    expect($user->assignedPrincipalIds())->toEqualCanonicalizing([$first->id, $second->id])
        ->and($user->managesPrincipal($first->id))->toBeTrue()
        ->and($user->managesPrincipal(Principal::factory()->create()))->toBeFalse()
        ->and($first->marketingUsers()->pluck('users.id')->all())->toEqual([$user->id]);
});

it('rejects a duplicate assignment', function () {
    $user = User::factory()->create();
    $principal = Principal::factory()->create();

    $user->principals()->attach($principal->id);

    expect(fn () => $user->principals()->attach($principal->id))
        ->toThrow(QueryException::class);
});

it('saves principal assignments from the user form', function () {
    seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);

    $marketing = Department::factory()->create(['name' => Department::MARKETING]);
    $principal = Principal::factory()->create();

    livewire(EditUser::class, ['record' => User::factory()->create(['department_id' => $marketing->id])->getRouteKey()])
        ->fillForm(['principals' => [$principal->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($principal->marketingUsers()->count())->toBe(1);
});

it('offers principal responsibility to a super admin editing any user', function () {
    seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);

    livewire(EditUser::class, ['record' => User::factory()->create(['department_id' => null])->getRouteKey()])
        ->assertFormFieldIsVisible('principals');
});
