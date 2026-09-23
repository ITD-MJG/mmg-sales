<?php

use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Models\Department;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // A read-only global viewer: Director sitting in the Management department.
    $management = Department::factory()->create(['name' => Department::MANAGEMENT]);
    $this->bodUser = User::factory()->create(['department_id' => $management->id]);
    $this->bodUser->assignRole('Management Director');
});

it('allows a Management Director to access the lead list but forbids create and edit pages', function () {
    $lead = Lead::factory()->create();

    actingAs($this->bodUser);

    // Can access list
    $this->get(LeadResource::getUrl('index'))
        ->assertSuccessful();

    // Forbidden from create
    $this->get(LeadResource::getUrl('create'))
        ->assertForbidden();

    // Forbidden from edit
    $this->get(LeadResource::getUrl('edit', ['record' => $lead]))
        ->assertForbidden();
});

it('hides create and edit actions from the lead table for a Management Director but shows view', function () {
    $lead = Lead::factory()->create();

    actingAs($this->bodUser);

    Livewire::test(ListLeads::class)
        ->assertActionHidden('create')
        ->assertTableActionHidden('edit', $lead)
        ->assertTableActionVisible('view', $lead);
});
