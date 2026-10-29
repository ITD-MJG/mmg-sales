<?php

use App\Filament\Resources\Activities\Tables\ActivitiesTable;
use App\Filament\Resources\Leads\Tables\LeadsTable;
use App\Models\Activity;
use App\Models\Department;
use App\Models\Lead;
use App\Models\Role;
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
    Role::findOrCreate('Sales Representative');
});

function visibleLeadIds(User $user): array
{
    Auth::login($user);
    $query = Lead::query();
    LeadsTable::listVisibilityQuery($query, $user);
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
it('shows all leads to users in the Marketing department regardless of role', function () {
    $marketingDepartment = Department::factory()->create(['name' => 'Marketing']);
    $marketingUser = User::factory()->create(['department_id' => $marketingDepartment->id]);
    $marketingUser->assignRole('Marketing Staff');
    $otherUser = User::factory()->create();
    $otherLead = Lead::factory()->create(['created_by' => $otherUser->id]);

    expect(visibleLeadIds($marketingUser))->toContain($otherLead->id)
        ->and(app(LeadPolicy::class)->view($marketingUser, $otherLead))->toBeTrue();
});

it('keeps leads scoped for users outside the Marketing department', function () {
    $salesDepartment = Department::factory()->create(['name' => 'Sales']);
    $user = User::factory()->create(['department_id' => $salesDepartment->id]);
    $user->assignRole('Sales Representative');
    $otherUser = User::factory()->create();
    $otherLead = Lead::factory()->create(['created_by' => $otherUser->id]);

    expect(visibleLeadIds($user))->not->toContain($otherLead->id)
        ->and(app(LeadPolicy::class)->view($user, $otherLead))->toBeFalse();
});

/**
 * A Director in the Management department is a global viewer: the query scope
 * in LeadsTable::listVisibilityQuery() already returns every lead, so the
 * record policy must agree or the list loads with no openable record.
 */
function managementDirector(): User
{
    $management = Department::factory()->create(['name' => Department::MANAGEMENT]);
    $director = User::factory()->create(['department_id' => $management->id]);
    $director->assignRole('Management Director');
    $director->givePermissionTo(['view_lead', 'view_any_lead', 'update_lead', 'delete_lead']);

    return $director;
}

it('lets a Management director view leads they did not create', function () {
    $director = managementDirector();
    $salesRep = User::factory()->create();
    $salesRep->assignRole('Sales Representative');
    $lead = Lead::factory()->create(['created_by' => $salesRep->id]);

    expect($director->hasGlobalVisibility())->toBeTrue()
        ->and($director->isReadOnlyGlobalViewer())->toBeTrue()
        ->and(visibleLeadIds($director))->toContain($lead->id)
        ->and(app(LeadPolicy::class)->view($director, $lead))->toBeTrue();
});

it('keeps a Management director read-only on leads they did not create', function () {
    $director = managementDirector();
    $salesRep = User::factory()->create();
    $salesRep->assignRole('Sales Representative');
    $lead = Lead::factory()->create(['created_by' => $salesRep->id]);

    $policy = app(LeadPolicy::class);

    expect($policy->update($director, $lead))->toBeFalse()
        ->and($policy->delete($director, $lead))->toBeFalse()
        ->and($policy->before($director, 'update'))->toBeFalse()
        ->and($policy->before($director, 'delete'))->toBeFalse();
});
