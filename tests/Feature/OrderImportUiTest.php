<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('shows import action to Super Admin', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    actingAs($user);

    Livewire::test(ListOrders::class)
        ->assertActionVisible('import');
});

it('shows import action to Regional Sales Manager', function () {
    $user = User::factory()->create();
    $user->assignRole('Regional Sales Manager');

    actingAs($user);

    Livewire::test(ListOrders::class)
        ->assertActionVisible('import');
});

it('hides import action from Sales Representative', function () {
    $user = User::factory()->create();
    $user->assignRole('Sales Staff');

    actingAs($user);

    Livewire::test(ListOrders::class)
        ->assertActionHidden('import');
});
