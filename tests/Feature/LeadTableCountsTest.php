<?php

namespace Tests\Feature;

use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Models\Activity;
use App\Models\ActivityComment;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('Super Admin');
    actingAs($this->user);
});

it('shows activity and comment counts, and hides opportunities by default', function () {
    $lead = Lead::factory()->create();

    $activities = Activity::factory()->count(2)->forLead($lead)->create();

    ActivityComment::factory()->count(3)->create(['activity_id' => $activities->first()->id]);

    livewire(ListLeads::class)
        ->assertTableColumnStateSet('activities_count', 2, $lead)
        ->assertTableColumnStateSet('activity_comments_count', 3, $lead)
        ->assertTableColumnExists('opportunities_count')
        ->assertTableColumnVisible('activities_count')
        ->assertTableColumnVisible('activity_comments_count');

    expect(livewire(ListLeads::class)->instance()->getTable()->getColumn('opportunities_count')->isToggledHiddenByDefault())
        ->toBeTrue();
});
