<?php

use App\Models\Department;
use App\Models\Principal;
use App\Models\User;
use Database\Seeders\MarketingPrincipalAssignmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('assigns the agreed principals by name and is idempotent', function () {
    $marketing = Department::factory()->create(['name' => Department::MARKETING]);
    $ahmad = User::factory()->create(['name' => 'Ahmad Soni', 'department_id' => $marketing->id]);
    User::factory()->create(['name' => 'Reza Muhammad Rifki', 'department_id' => $marketing->id]);

    foreach (['Sysmex', 'Genolution', 'Genesystem', 'Mandelab', 'Tecan', 'AFI', 'Pekybio', 'Infitek', 'Abbott'] as $name) {
        Principal::factory()->create(['name' => $name]);
    }

    $this->seed(MarketingPrincipalAssignmentSeeder::class);
    $this->seed(MarketingPrincipalAssignmentSeeder::class);

    expect($ahmad->principals()->pluck('name')->sort()->values()->all())
        ->toEqual(['Genesystem', 'Genolution', 'Mandelab', 'Sysmex', 'Tecan'])
        ->and($ahmad->principals()->count())->toBe(5);
});

it('skips users that do not exist', function () {
    Principal::factory()->create(['name' => 'Sysmex']);

    $this->seed(MarketingPrincipalAssignmentSeeder::class);

    expect(User::query()->count())->toBe(0);
});
