<?php

use App\Models\Department;
use App\Models\Position;
use App\Models\Role;
use Database\Seeders\DepartmentPositionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('seeds the Management Director role with only view permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $role = Role::where('name', 'Management Director')->first();

    expect($role)->not->toBeNull()
        ->and($role->permissions)->not->toBeEmpty();

    // The read-only global viewer may look at everything and change nothing:
    // every permission it holds must be a view permission.
    foreach ($role->permissions as $permission) {
        expect($permission->name)->toStartWith('view');
    }

    $nonViewPermissions = Permission::where('name', 'not like', 'view_%')->get();

    foreach ($nonViewPermissions as $permission) {
        expect($role->hasPermissionTo($permission))->toBeFalse();
    }
});

it('seeds the management department and its director position correctly', function () {
    $this->seed([
        DepartmentPositionSeeder::class,
    ]);

    $department = Department::where('name', 'Management')->first();

    expect($department)->not->toBeNull();

    $position = Position::where('name', 'Managing Director')->first();

    expect($position)->not->toBeNull()
        ->and($position->department_id)->toBe($department->id);
});

it('does not seed a phantom Board of Director role', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Read-only global visibility is served by Management Director. A separate
    // "Board of Director" role existed only in tests and one dead migration.
    expect(Role::where('name', 'Board of Director')->exists())->toBeFalse();
});
