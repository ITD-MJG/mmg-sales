<?php

use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Livewire\LeadBoard;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('Super Admin');
});

it('renders table and board tabs on the leads list', function () {
    actingAs($this->user);

    Livewire::test(ListLeads::class)
        ->assertSuccessful()
        ->assertSee('Table')
        ->assertSee('Board');
});

it('mounts the board component and renders status columns', function () {
    actingAs($this->user);

    Lead::factory()->create(['status' => 'new', 'created_by' => $this->user->id]);
    Lead::factory()->create(['status' => 'closed_lost', 'created_by' => $this->user->id]);

    Livewire::test(LeadBoard::class)
        ->assertSuccessful()
        ->assertSee('New')
        ->assertSee('Closed (Lost)');
});

it('displays collaborator summary in board', function () {
    actingAs($this->user);

    $lead = Lead::factory()->create(['status' => 'new', 'created_by' => $this->user->id]);
    $collaborators = User::factory()->count(3)->create();
    $lead->collaborators()->attach($collaborators->pluck('id'));

    Livewire::test(LeadBoard::class)
        ->assertSee($collaborators[0]->name.' + 2')
        ->assertSee($collaborators[1]->name)
        ->assertSee($collaborators[2]->name);
});

it('board shows every lead to a super admin', function () {
    actingAs($this->user);

    $other = User::factory()->create();
    $other->assignRole('Sales Staff');

    Lead::factory()->create(['status' => 'new', 'created_by' => $this->user->id]);
    Lead::factory()->create(['status' => 'new', 'created_by' => $other->id]);

    $records = Livewire::test(LeadBoard::class)->instance()->getBoard()->getBoardRecords('new');

    expect($records)->toHaveCount(2);
});

it('scopes the board to own leads for staff', function () {
    $staff = User::factory()->create();
    $staff->assignRole('Sales Staff');
    actingAs($staff);

    $other = User::factory()->create();
    $other->assignRole('Sales Staff');

    Lead::factory()->create(['status' => 'new', 'created_by' => $staff->id]);
    Lead::factory()->create(['status' => 'new', 'created_by' => $other->id]);

    $records = Livewire::test(LeadBoard::class)->instance()->getBoard()->getBoardRecords('new');

    expect($records)->toHaveCount(1);
});

it('switching viewTab to board keeps the page successful', function () {
    actingAs($this->user);

    Lead::factory()->create(['status' => 'in_process', 'created_by' => $this->user->id]);

    Livewire::test(ListLeads::class)
        ->set('viewTab', 'board')
        ->assertSuccessful();
});

it('moves a lead card between status columns', function () {
    actingAs($this->user);

    $lead = Lead::factory()->create(['status' => 'new', 'created_by' => $this->user->id]);

    Livewire::test(LeadBoard::class)
        ->call('moveCard', (string) $lead->getKey(), 'in_process')
        ->assertSuccessful();

    expect($lead->fresh()->status)->toBe('in_process')
        ->and($lead->fresh()->position)->not->toBeNull();
});
