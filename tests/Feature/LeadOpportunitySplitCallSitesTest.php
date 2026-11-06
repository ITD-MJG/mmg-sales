<?php

namespace Tests\Feature;

use App\DTOs\ReportFilterData;
use App\Filament\Resources\Activities\Tables\ActivitiesTable;
use App\Models\Activity;
use App\Models\Department;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Position;
use App\Models\User;
use App\Services\Reports\PipelineReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * The lead/opportunity split renamed `lead_collaborators` to
 * `opportunity_collaborators` and moved the deal columns onto `opportunities`.
 * These tests pin the call sites that were left pointing at the old shape.
 */
it('keeps a collaborators table for each side of the split', function () {
    // The split renamed lead_collaborators to opportunity_collaborators, then
    // 2026_10_21_000001 re-created lead_collaborators because leads staff
    // their own people again. Both tables are current; neither is stale.
    expect(Schema::hasTable('opportunity_collaborators'))->toBeTrue()
        ->and(Schema::hasTable('lead_collaborators'))->toBeTrue();
});

it('scopes activities by opportunity collaboration without erroring', function () {
    $user = User::factory()->create();
    $opportunity = Opportunity::factory()->create();
    $opportunity->collaborators()->attach($user->id, ['added_by' => $user->id]);

    $visible = Activity::factory()->forOpportunity($opportunity)->create(['user_id' => $user->id]);

    $other = Opportunity::factory()->create();
    $hidden = Activity::factory()->forOpportunity($other)->create();

    actingAs($user);

    $query = Activity::query();
    ActivitiesTable::listVisibilityQuery($query);

    expect($query->pluck('id')->all())->toContain($visible->id)
        ->and($query->pluck('id')->all())->not->toContain($hidden->id);
});

it('still scopes activities by lead ownership', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create(['created_by' => $user->id]);

    $visible = Activity::factory()->forLead($lead)->create();
    $hidden = Activity::factory()->forLead(Lead::factory()->create())->create();

    actingAs($user);

    $query = Activity::query();
    ActivitiesTable::listVisibilityQuery($query);

    expect($query->pluck('id')->all())->toContain($visible->id)
        ->and($query->pluck('id')->all())->not->toContain($hidden->id);
});

it('builds the lead report from leads, not opportunities', function () {
    $department = Department::factory()->create(['name' => 'Sales', 'code' => 'SAL']);
    $position = Position::factory()->create([
        'name' => 'Sales Representative',
        'department_id' => $department->id,
    ]);
    $rep = User::factory()->create([
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);

    $lead = Lead::factory()->create([
        'status' => 'in_process',
        'estimated_revenue' => 5_000_000,
    ]);
    $lead->collaborators()->attach($rep->id, ['added_by' => $rep->id]);

    // An opportunity matching the same filters must not leak into the report:
    // the Lead Report reports on leads only.
    Opportunity::factory()->stage('won')->create(['estimated_revenue' => 9_000_000]);

    $filters = ReportFilterData::fromArray([
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
    ]);

    $data = app(PipelineReportService::class)->generate($filters);

    expect($data->totalLeads)->toBe(1)
        ->and($data->openLeads)->toBe(1)
        ->and($data->pipelineValue)->toBe(5_000_000.0)
        ->and($data->leadsByStatus->firstWhere('status', 'in_process')['count'])->toBe(1)
        ->and($data->leadsBySalesRep)->toHaveCount(1)
        ->and($data->leadsBySalesRep->first()['name'])->toBe($rep->name)
        ->and($data->leadsBySalesRep->first()['value'])->toBe(5_000_000.0);
});
