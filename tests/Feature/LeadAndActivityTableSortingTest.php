<?php

namespace Tests\Feature;

use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('Super Admin');
    Livewire::actingAs($this->user);
});

it('sorts the leads table by customer name ascending and descending', function () {
    $customer = Customer::factory()->create();
    $alpha = Lead::factory()->create([
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
        'customer_name' => 'Alpha sorting lead',
    ]);
    $zulu = Lead::factory()->create([
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
        'customer_name' => 'Zulu sorting lead',
    ]);

    Livewire::test(ListLeads::class)
        ->sortTable('customer_name', 'asc')
        ->assertCanSeeTableRecords([$alpha, $zulu], inOrder: true)
        ->sortTable('customer_name', 'desc')
        ->assertCanSeeTableRecords([$zulu, $alpha], inOrder: true);
});

it('sorts the activities table by date ascending and descending', function () {
    $olderLead = Lead::factory()->create(['created_by' => $this->user->id]);
    $newerLead = Lead::factory()->create(['created_by' => $this->user->id]);
    $older = Activity::factory()->forLead($olderLead)->create([
        'user_id' => $this->user->id,
        'performed_at' => now()->subDay(),
    ]);
    $newer = Activity::factory()->forLead($newerLead)->create([
        'user_id' => $this->user->id,
        'performed_at' => now(),
    ]);

    Livewire::test(ListActivities::class)
        ->sortTable('performed_at', 'asc')
        ->assertCanSeeTableRecords([$older, $newer], inOrder: true)
        ->sortTable('performed_at', 'desc')
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
});

it('lists activities newest first by default', function () {
    $olderLead = Lead::factory()->create(['created_by' => $this->user->id]);
    $newerLead = Lead::factory()->create(['created_by' => $this->user->id]);
    $older = Activity::factory()->forLead($olderLead)->create([
        'user_id' => $this->user->id,
        'performed_at' => now()->subDay(),
    ]);
    $newer = Activity::factory()->forLead($newerLead)->create([
        'user_id' => $this->user->id,
        'performed_at' => now(),
    ]);

    Livewire::test(ListActivities::class)
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
});
