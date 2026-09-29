<?php

use App\Filament\Resources\Opportunities\Pages\ViewOpportunity;
use App\Models\Opportunity;
use App\Models\User;
use App\Policies\OpportunityPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('creator can add collaborator to opportunity', function () {
    $creator = User::factory()->create();
    $collaborator = User::factory()->create();

    $opportunity = Opportunity::factory()->create([
        'created_by' => $creator->id,
    ]);

    $this->actingAs($creator);

    $opportunity->collaborators()->attach($collaborator->id, ['added_by' => $creator->id]);

    expect($opportunity->collaborators)->toHaveCount(1);
});

it('non-creator cannot add collaborator to opportunity', function () {
    $creator = User::factory()->create();
    $otherUser = User::factory()->create();

    $opportunity = Opportunity::factory()->create([
        'created_by' => $creator->id,
    ]);

    $this->actingAs($otherUser);

    $policy = new OpportunityPolicy;

    expect($policy->addCollaborator($otherUser, $opportunity))->toBeFalse();
});

it('creator can remove collaborator from opportunity', function () {
    $creator = User::factory()->create();
    $collaborator = User::factory()->create();

    $opportunity = Opportunity::factory()->create([
        'created_by' => $creator->id,
    ]);

    $opportunity->collaborators()->attach($collaborator->id, ['added_by' => $creator->id]);

    $this->actingAs($creator);

    $opportunity->collaborators()->detach($collaborator->id);

    expect($opportunity->collaborators)->toHaveCount(0);
});

it('collaborator can comment on an activity attached to the opportunity', function () {
    $creator = User::factory()->create();
    $collaborator = User::factory()->create();

    $opportunity = Opportunity::factory()->create([
        'created_by' => $creator->id,
    ]);

    $opportunity->collaborators()->attach($collaborator->id, ['added_by' => $creator->id]);

    // Access flows through the opportunity, not the lead-only policy ability.
    expect($opportunity->isAccessibleBy($collaborator))->toBeTrue();
});
it('non-collaborator cannot comment on an activity attached to the opportunity', function () {
    $creator = User::factory()->create();
    $otherUser = User::factory()->create();

    $opportunity = Opportunity::factory()->create([
        'created_by' => $creator->id,
    ]);

    expect($opportunity->isAccessibleBy($otherUser))->toBeFalse();
});

it('renders Sales Rep collaborators once on opportunity view page', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $creator = User::factory()->create();
    $creator->assignRole('Super Admin');

    $collaborators = collect(['Alice Rep', 'Bob Rep', 'Carol Rep'])
        ->map(fn (string $name) => User::factory()->create(['name' => $name]));

    $opportunity = Opportunity::factory()->create([
        'created_by' => $creator->id,
        'customer_id' => null,
    ]);

    $opportunity->collaborators()->attach(
        $collaborators->pluck('id')->all(),
        ['added_by' => $creator->id],
    );

    actingAs($creator);

    $html = Livewire::test(ViewOpportunity::class, ['record' => $opportunity->getRouteKey()])
        ->assertSuccessful()
        ->html();

    // Each collaborator name must appear exactly once — a duplicated entry
    // would render the whole list once per collaborator.
    foreach ($collaborators as $collaborator) {
        expect(substr_count($html, $collaborator->name))->toBe(1);
    }
});
