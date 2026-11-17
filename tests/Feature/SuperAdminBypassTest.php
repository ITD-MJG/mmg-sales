<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
});

it('bypasses direct permission checks for a super admin', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('Super Admin');

    expect($superAdmin->hasPermissionTo('create_activity_comment'))->toBeTrue()
        ->and($superAdmin->hasPermissionTo('view_sales_reports'))->toBeTrue()
        ->and($superAdmin->hasAnyPermission('import_customers', 'update_payment_order'))->toBeTrue();
});

it('does not bypass direct permission checks for a normal user', function () {
    $user = User::factory()->create();

    expect($user->hasPermissionTo('create_activity_comment'))->toBeFalse();
});

it('can view every Filament resource', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('Super Admin');
    auth()->login($superAdmin);

    $denied = collect(Filament::getPanel('admin')->getResources())
        ->reject(fn (string $resource): bool => $resource::canViewAny())
        ->values()
        ->all();

    expect($denied)->toBe([]);
});
