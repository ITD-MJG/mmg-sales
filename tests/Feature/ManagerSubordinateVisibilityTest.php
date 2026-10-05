<?php

use App\Filament\Resources\Activities\Tables\ActivitiesTable;
use App\Filament\Resources\Leads\Tables\LeadsTable;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\Position;
use App\Models\Territory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
});

/** Run the leads listing query for the given user and return the visible IDs. */
function visibleLeadIds(User $user, string $column = 'created_by'): array
{
    Auth::login($user);

    $query = Lead::query();
    LeadsTable::applyVisibilityScope($query, $column);
    $ids = $query->pluck('id')->all();

    Auth::logout();

    return $ids;
}

/** Run the activities listing query for the given user and return the visible IDs. */
function visibleActivityIds(User $user): array
{
    Auth::login($user);

    $query = Activity::query();
    ActivitiesTable::listVisibilityQuery($query);
    $ids = $query->pluck('id')->all();

    Auth::logout();

    return $ids;
}

/**
 * A Sales Manager whose position branch contains a Sales Staff subordinate,
 * plus a direct report attached via manager_id.
 *
 * @param  int|null  $territoryId  Territory shared by the team.
 * @param  bool  $managerHasTerritory  When false the manager is left with no territory.
 */
function managerWithTeam(?int $territoryId, bool $managerHasTerritory = true): array
{
    $parentPosition = Position::factory()->create(['name' => 'Sales Manager']);
    $childPosition = Position::factory()->create([
        'name' => 'Sales Staff',
        'parent_id' => $parentPosition->id,
    ]);

    $manager = User::factory()->create([
        'position_id' => $parentPosition->id,
        'territory_id' => $managerHasTerritory ? $territoryId : null,
    ]);
    $manager->assignRole('Sales Manager');

    $subordinate = User::factory()->create([
        'position_id' => $childPosition->id,
        'territory_id' => $territoryId,
    ]);
    $subordinate->assignRole('Sales Staff');

    $directReport = User::factory()->create([
        'position_id' => $childPosition->id,
        'territory_id' => $territoryId,
        'manager_id' => $manager->id,
    ]);
    $directReport->assignRole('Sales Staff');

    return [$manager, $subordinate, $directReport];
}

it('shows subordinate leads when manager and team share a territory', function () {
    $territory = Territory::factory()->create();
    [$manager, $subordinate, $directReport] = managerWithTeam($territory->id);

    $subordinateLead = Lead::factory()->create(['created_by' => $subordinate->id]);
    $directReportLead = Lead::factory()->create(['created_by' => $directReport->id]);

    $ids = visibleLeadIds($manager);

    expect($ids)->toContain($subordinateLead->id)
        ->and($ids)->toContain($directReportLead->id);
});

it('shows subordinate leads when the manager has no territory assigned', function () {
    $territory = Territory::factory()->create();
    [$manager, $subordinate, $directReport] = managerWithTeam($territory->id, managerHasTerritory: false);

    $subordinateLead = Lead::factory()->create(['created_by' => $subordinate->id]);
    $directReportLead = Lead::factory()->create(['created_by' => $directReport->id]);

    $ids = visibleLeadIds($manager);

    expect($ids)->toContain($subordinateLead->id)
        ->and($ids)->toContain($directReportLead->id);
});

it('shows subordinate activities when the manager has no territory assigned', function () {
    $territory = Territory::factory()->create();
    [$manager, $subordinate, $directReport] = managerWithTeam($territory->id, managerHasTerritory: false);

    $subordinateLead = Lead::factory()->create(['created_by' => $subordinate->id]);
    $subordinateActivity = Activity::factory()->forLead($subordinateLead)->create([
        'user_id' => $subordinate->id,
    ]);

    $directReportLead = Lead::factory()->create(['created_by' => $directReport->id]);
    $directReportActivity = Activity::factory()->forLead($directReportLead)->create([
        'user_id' => $directReport->id,
    ]);

    $ids = visibleActivityIds($manager);

    expect($ids)->toContain($subordinateActivity->id)
        ->and($ids)->toContain($directReportActivity->id);
});

it('still hides leads from another territory', function () {
    $territory = Territory::factory()->create();
    $otherTerritory = Territory::factory()->create();
    [$manager] = managerWithTeam($territory->id);

    $outsiderLead = Lead::factory()->create([
        'created_by' => User::factory()->create(['territory_id' => $otherTerritory->id])->id,
    ]);

    expect(visibleLeadIds($manager))->not->toContain($outsiderLead->id);
});

it('still restricts Sales Staff to their own records', function () {
    $territory = Territory::factory()->create();
    [, $staff, $peer] = managerWithTeam($territory->id);
    $staff->assignRole('Sales Staff');

    $ownLead = Lead::factory()->create(['created_by' => $staff->id]);
    $peerLead = Lead::factory()->create(['created_by' => $peer->id]);

    $ids = visibleLeadIds($staff);

    expect($ids)->toContain($ownLead->id)
        ->and($ids)->not->toContain($peerLead->id);
});
