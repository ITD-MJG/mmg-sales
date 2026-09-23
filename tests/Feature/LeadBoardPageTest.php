<?php

use App\Filament\Pages\KanbanLeads;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('Sales Staff');
});

it('renders kanban board columns for lead statuses', function (): void {
    actingAs($this->user);

    Lead::factory()->create(['status' => 'new', 'created_by' => $this->user->id]);
    Lead::factory()->create(['status' => 'won', 'created_by' => $this->user->id]);

    Livewire::test(KanbanLeads::class)
        ->assertSuccessful()
        ->assertSee('New')
        ->assertSee('Won');
});

it('keeps visibility scope: sales staff only sees own leads on board', function (): void {
    actingAs($this->user);

    $other = User::factory()->create();
    $other->assignRole('Sales Staff');

    Lead::factory()->create(['status' => 'new', 'created_by' => $this->user->id]);
    Lead::factory()->create(['status' => 'new', 'created_by' => $other->id]);

    $component = Livewire::test(KanbanLeads::class);

    $boardRecords = $component->instance()->getBoard()->getBoardRecords('new');

    expect($boardRecords)->toHaveCount(1)
        ->and($boardRecords->first()->created_by)->toBe($this->user->id);
});

it('panel pages no longer register hidden LeadBoard', function (): void {
    $panel = Filament::getPanel('admin');

    expect($panel->getPages())
        ->not->toContain(App\Filament\Pages\LeadBoard::class);
});
