<?php

namespace Tests\Feature;

use App\Filament\Resources\Distributors\DistributorResource;
use App\Models\Distributor;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('Super Admin');

    $this->salesRep = User::factory()->create();
    $this->salesRep->assignRole('Sales Staff');
});

it('allows Super Admin to view distributors', function () {
    actingAs($this->superAdmin);

    expect($this->superAdmin->can('viewAny', Distributor::class))->toBeTrue();
    expect(DistributorResource::shouldRegisterNavigation())->toBeTrue();
});

it('allows Sales Staff to view distributors as reference data', function () {
    actingAs($this->salesRep);

    // Reference data is viewable by every Sales & Marketing role (view_any_distributor
    // ships in the shared reference permission set), which matches production.
    expect($this->salesRep->can('viewAny', Distributor::class))->toBeTrue();
    expect(DistributorResource::shouldRegisterNavigation())->toBeTrue();
});
