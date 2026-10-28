<?php

use App\Filament\Resources\Activities\Tables\ActivitiesTable;
use App\Filament\Resources\Leads\Tables\LeadsTable;
use App\Models\Activity;

use App\Models\Lead;
use App\Models\Territory;
use App\Models\User;
use App\Policies\LeadPolicy;
use App\Services\ActivityScopeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
    \App\Models\Role::findOrCreate('Sales Representative');
});

function visibleLeadIds(User $user, string $column = 'created_by'): array
{
    Auth::login($user);
    $query = Lead::query();
    LeadsTable::applyVisibilityScope($query, $column);
    $ids = $query->pluck('id')->all();
    Auth::logout();

    return $ids;
}

function visibleActivityIds(User $user): array
{
    Auth::login($user);
    $query = Activity::query();
    ActivitiesTable::listVisibilityQuery($query);
    $ids = $query->pluck('id')->all();
    Auth::logout();

    return $ids;
}

/** @return array{0: User, 1: User, 2: User} */
function managerWithTeam(?int $territoryId): array
{
    $manager = User::factory()->create(['territory_id' => $territoryId]);
    $manager->assignRole('Sales Manager');

    $subordinate = User::factory()->create([
        'territory_id' => $territoryId,
        'manager_id' => $manager->id,
    ]);
    $subordinate->assignRole('Sales Staff');

    $directReport = User::factory()->create([
        'territory_id' => $territoryId,
        'manager_id' => $subordinate->id,
    ]);
    $directReport->assignRole('Sales Staff');

    return [$manager, $subordinate, $directReport];
}

it('shows leads for direct and indirect reports across territories', function () {
    $managerTerritory = Territory::factory()->create();
    $otherTerritory = Territory::factory()->create();
    [$manager, $subordinate, $indirectReport] = managerWithTeam($managerTerritory->id);
    expect($manager->subordinateUserIds())->toContain($subordinate->id, $indirectReport->id);
    $subordinate->assignRole('Sales Representative');
    $indirectReport->assignRole('Sales Representative');
    $subordinate->update(['territory_id' => $otherTerritory->id]);
    $indirectReport->update(['territory_id' => $otherTerritory->id]);

    $subordinateLead = Lead::factory()->create(['created_by' => $subordinate->id]);
    $indirectLead = Lead::factory()->create(['created_by' => $indirectReport->id]);
    $unrelatedUser = User::factory()->create(['territory_id' => $otherTerritory->id]);
    $unrelatedUser->assignRole('Sales Staff');
    $unrelatedLead = Lead::factory()->create(['created_by' => $unrelatedUser->id]);

    expect(visibleLeadIds($manager))->toContain($subordinateLead->id, $indirectLead->id)
        ->and(visibleLeadIds($manager))->not->toContain($unrelatedLead->id)
        ->and(app(LeadPolicy::class)->view($manager, $subordinateLead))->toBeTrue()
        ->and(app(LeadPolicy::class)->view($manager, $unrelatedLead))->toBeFalse();
});

it('shows subordinate activities consistently in the list and widgets and allows team comments', function () {
    $managerTerritory = Territory::factory()->create();
    $otherTerritory = Territory::factory()->create();
    [$manager, $subordinate] = managerWithTeam($managerTerritory->id);
    $subordinate->update(['territory_id' => $otherTerritory->id]);
    $lead = Lead::factory()->create(['created_by' => $subordinate->id]);
    $activity = Activity::factory()->forLead($lead)->create(['user_id' => $subordinate->id]);

    expect(visibleActivityIds($manager))->toContain($activity->id)
        ->and(app(ActivityScopeService::class)->getActivityQuery($manager)->pluck('id')->all())->toContain($activity->id)
        ->and($activity->isAccessibleBy($manager))->toBeTrue();
});

it('keeps staff visibility limited to their own leads', function () {
    [, $staff, $peer] = managerWithTeam(null);
    $staff->assignRole('Sales Representative');
    $ownLead = Lead::factory()->create(['created_by' => $staff->id]);
    $peerLead = Lead::factory()->create(['created_by' => $peer->id]);

    expect(visibleLeadIds($staff))->toContain($ownLead->id)
        ->and(visibleLeadIds($staff))->not->toContain($peerLead->id);
});

it('keeps Marketing Staff visibility limited to their own leads', function () {
    $marketingStaff = User::factory()->create();
    $marketingStaff->assignRole('Marketing Staff');
    $ownLead = Lead::factory()->create(['created_by' => $marketingStaff->id]);
    $otherLead = Lead::factory()->create(['created_by' => User::factory()->create()->id]);

    expect(visibleLeadIds($marketingStaff))->toContain($ownLead->id)
        ->and(visibleLeadIds($marketingStaff))->not->toContain($otherLead->id);
});
