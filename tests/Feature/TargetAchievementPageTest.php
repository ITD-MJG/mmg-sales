<?php

use App\Filament\Resources\Reports\Pages\TargetAchievementPage;
use App\Models\Department;
use App\Models\Target;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $management = Department::factory()->create(['name' => Department::MANAGEMENT]);
    $this->user = User::factory()->create(['department_id' => $management->id]);
    $this->user->assignRole('Management Director');
});

it('renders the target achievement page', function (): void {
    actingAs($this->user);

    Target::create([
        'user_id' => $this->user->id,
        'year' => now()->year,
        'month' => now()->month,
        'monthly_target' => 100000,
    ]);

    Livewire::test(TargetAchievementPage::class)
        ->assertSuccessful()
        ->assertSee('Target vs Orders')
        ->assertSee('Target vs Leads');
});

it('grants the target achievement page permission to a Management director', function (): void {
    expect($this->user->can('view_report_target_achievement'))->toBeTrue();
});
