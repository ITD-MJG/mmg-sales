<?php

namespace Tests\Feature;

use App\Filament\Resources\Distributors\DistributorResource;
use App\Models\Distributor;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('Super Admin');

    // 'Sales Representative' is a live production role the seeder does not
    // manage (it creates 'Sales Staff' instead). Reproduce its real shape: it
    // holds a broad permission set, and the seeder must still revoke only the
    // distributor view grants from it.
    $salesRepRole = Role::findOrCreate('Sales Representative');
    $salesRepRole->syncPermissions(['view_any_distributor', 'view_distributor', 'view_any_product']);

    $this->salesRep = User::factory()->create();
    $this->salesRep->assignRole('Sales Representative');

    // Re-run the seeder now that the role exists, so its cleanup block applies.
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('allows Super Admin to view distributors', function () {
    actingAs($this->superAdmin);

    expect($this->superAdmin->can('viewAny', Distributor::class))->toBeTrue()
        ->and(DistributorResource::canAccess())->toBeTrue();
});

it('denies Sales Representative from viewing distributors', function () {
    actingAs($this->salesRep);

    expect($this->salesRep->can('viewAny', Distributor::class))->toBeFalse()
        ->and(DistributorResource::canAccess())->toBeFalse();
});

it('revokes only the distributor grants, leaving other Sales Rep permissions intact', function () {
    $role = Role::where('name', 'Sales Representative')->firstOrFail();

    expect($role->hasPermissionTo('view_any_distributor'))->toBeFalse()
        ->and($role->hasPermissionTo('view_distributor'))->toBeFalse()
        ->and($role->hasPermissionTo('view_any_product'))->toBeTrue();
});

it('keeps distributor access for the master-data owner and global viewer roles', function () {
    foreach (['Import & Purchasing Supervisor', 'Management Director'] as $roleName) {
        expect(Role::where('name', $roleName)->firstOrFail()->hasPermissionTo('view_any_distributor'))
            ->toBeTrue("{$roleName} lost distributor access");
    }
});
