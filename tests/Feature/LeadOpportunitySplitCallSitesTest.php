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
it('does not query the dropped lead_collaborators table', function () {
    expect(Schema::hasTable('opportunity_collaborators'))->toBeTrue()
        ->and(Schema::hasTable('lead_collaborators'))->toBeFalse();
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

it('builds the pipeline report from opportunities', function () {
    $department = Department::factory()->create(['name' => 'Sales', 'code' => 'SAL']);
    $position = Position::factory()->create([
        'name' => 'Sales Representative',
        'department_id' => $department->id,
    ]);
    $rep = User::factory()->create([
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);

    $opportunity = Opportunity::factory()->stage('won')->create([
        'estimated_revenue' => 5_000_000,
    ]);
    $opportunity->collaborators()->attach($rep->id, ['added_by' => $rep->id]);

    $filters = ReportFilterData::fromArray([
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
    ]);

    $data = app(PipelineReportService::class)->generate($filters);

    expect($data->totalProjects)->toBe(1)
        ->and($data->wonProjects)->toBe(1)
        ->and($data->totalPipelineValue)->toBe(5_000_000.0)
        ->and($data->pipelineByStatus->first()['status'])->toBe('Won')
        ->and($data->pipelineBySalesRep)->toHaveCount(1)
        ->and($data->pipelineBySalesRep->first()['name'])->toBe($rep->name);
});
