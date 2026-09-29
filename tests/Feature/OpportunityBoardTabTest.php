<?php

use App\Filament\Resources\Opportunities\Pages\ListOpportunities;
use App\Livewire\OpportunityBoard;
use App\Models\Opportunity;
use App\Models\User;
use Filament\Facades\Filament;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('Sales Staff');
});

it('renders table and board tabs on the opportunities list', function () {
    actingAs($this->user);

    Livewire::test(ListOpportunities::class)
        ->assertSuccessful()
        ->assertSee('Table')
        ->assertSee('Board');
});

it('mounts the board component and renders stage columns', function () {
    actingAs($this->user);

    Opportunity::factory()->create(['stage' => 'new', 'created_by' => $this->user->id]);
    Opportunity::factory()->create(['stage' => 'won', 'created_by' => $this->user->id]);

    Livewire::test(OpportunityBoard::class)
        ->assertSuccessful()
        ->assertSee('New')
        ->assertSee('Won');
});

it('board scopes sales staff to their own opportunities', function () {
    actingAs($this->user);

    $other = User::factory()->create();
    $other->assignRole('Sales Staff');

    Opportunity::factory()->create(['stage' => 'new', 'created_by' => $this->user->id]);
    Opportunity::factory()->create(['stage' => 'new', 'created_by' => $other->id]);

    $records = Livewire::test(OpportunityBoard::class)->instance()->getBoard()->getBoardRecords('new');

    expect($records)->toHaveCount(1)
        ->and($records->first()->created_by)->toBe($this->user->id);
});

it('switching viewTab to board keeps the page successful', function () {
    actingAs($this->user);

    Opportunity::factory()->create(['stage' => 'qualified', 'created_by' => $this->user->id]);

    Livewire::test(ListOpportunities::class)
        ->set('viewTab', 'board')
        ->assertSuccessful();
});

it('panel no longer registers a standalone Lead Board page', function () {
    $panel = Filament::getPanel('admin');

    expect($panel->getPages())
        ->not->toContain(App\Filament\Pages\KanbanLeads::class);
});
