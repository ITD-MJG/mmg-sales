<?php

use App\Filament\Resources\Opportunities\OpportunityResource;
use App\Filament\Resources\Opportunities\Pages\ListOpportunities;
use App\Models\Opportunity;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('lists opportunities for a super admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    actingAs($admin);

    Livewire::test(ListOpportunities::class)->assertSuccessful();
});

it('lists opportunities for sales staff', function () {
    $staff = User::factory()->create();
    $staff->assignRole('Sales Staff');
    actingAs($staff);

    Livewire::test(ListOpportunities::class)->assertSuccessful();
});

it('denies a user with no roles from listing opportunities', function () {
    $nobody = User::factory()->create();
    actingAs($nobody);

    expect(OpportunityResource::canViewAny())->toBeFalse();

    $this->get(OpportunityResource::getUrl('index'))->assertForbidden();
});

it('forbids a non-privileged user from creating opportunities', function () {
    $nobody = User::factory()->create();
    actingAs($nobody);

    expect(OpportunityResource::canCreate())->toBeFalse();

    $this->get(OpportunityResource::getUrl('create'))->assertForbidden();
});

it('scopes the opportunity list to the creator for sales staff', function () {
    $staff = User::factory()->create();
    $staff->assignRole('Sales Staff');

    $other = User::factory()->create();
    $other->assignRole('Sales Staff');

    $own = Opportunity::factory()->create(['title' => 'Own Deal', 'created_by' => $staff->id]);
    $otherDeal = Opportunity::factory()->create(['title' => 'Other Deal', 'created_by' => $other->id]);

    actingAs($staff);

    Livewire::test(ListOpportunities::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$otherDeal]);
});
