<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Order;
use App\Models\Target;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * A role with no permissions renders an empty Filament navigation: every
 * resource's canViewAny() goes through BasePolicy::viewAny(), which needs
 * "view_any_{model}". A user on such a role sees only the Dashboard.
 *
 * The product ladder roles (Product Executive, Product Manager, Jr Product
 * Manager) are created by the Roles admin screen rather than by the seeder, so
 * they historically existed with zero permissions and were never backfilled.
 */
beforeEach(function () {
    // Reproduce the production shape: the roles exist before the seeder runs.
    foreach (['Product Executive', 'Jr Product Manager', 'Product Manager'] as $roleName) {
        SpatieRole::findOrCreate($roleName);
    }

    $this->seed(RolesAndPermissionsSeeder::class);
});

it('fills an empty product role with the product bundle', function (string $roleName) {
    $role = SpatieRole::findByName($roleName);

    expect($role->permissions()->count())->toBeGreaterThan(0)
        ->and($role->hasPermissionTo('view_any_lead'))->toBeTrue()
        ->and($role->hasPermissionTo('view_any_order'))->toBeTrue()
        ->and($role->hasPermissionTo('view_any_target'))->toBeTrue();
})->with([
    'Product Executive',
    'Jr Product Manager',
    'Product Manager',
]);

it('never overwrites a product role an operator already configured', function () {
    $role = SpatieRole::findByName('Product Executive');
    $role->syncPermissions(['view_any_lead']);

    $this->seed(RolesAndPermissionsSeeder::class);

    expect($role->fresh()->permissions()->pluck('name')->all())->toBe(['view_any_lead']);
});

it('lets a Product Executive reach the resources their bundle grants', function () {
    $user = User::factory()->create();
    $user->assignRole(SpatieRole::findByName('Product Executive'));

    actingAs($user);

    expect($user->can('viewAny', Lead::class))->toBeTrue()
        ->and($user->can('viewAny', Order::class))->toBeTrue()
        ->and($user->can('viewAny', Target::class))->toBeTrue()
        // Not part of the product bundle.
        ->and($user->can('viewAny', User::class))->toBeFalse();
});
