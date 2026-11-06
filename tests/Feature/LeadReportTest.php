<?php

use App\DTOs\PipelineReportData;
use App\DTOs\ReportFilterData;
use App\Models\Lead;
use App\Services\Reports\PipelineReportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
    Cache::flush();

    $this->filters = ReportFilterData::fromArray([
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
    ]);
});

function leadReport(): PipelineReportData
{
    return app(PipelineReportService::class)->generate(test()->filters);
}

it('counts leads by the lead status enum', function () {
    Lead::factory()->create(['status' => 'new']);
    Lead::factory()->create(['status' => 'in_process']);
    Lead::factory()->create(['status' => 'closed_lost']);

    $data = leadReport();

    expect($data->totalLeads)->toBe(3)
        ->and($data->openLeads)->toBe(2)
        ->and($data->disqualifiedLeads)->toBe(1);
});

it('groups leads by every status, including empty ones', function () {
    Lead::factory()->create(['status' => 'in_process']);

    $data = leadReport();

    expect($data->leadsByStatus)->toHaveCount(8)
        ->and($data->leadsByStatus->pluck('status')->all())
        ->toBe(array_keys(Lead::STATUS_LABELS))
        ->and($data->leadsByStatus->firstWhere('status', 'new')['count'])->toBe(0)
        ->and($data->leadsByStatus->firstWhere('status', 'in_process')['count'])->toBe(1);
});

it('sums pipeline value from open leads only', function () {
    Lead::factory()->create(['status' => 'new', 'estimated_revenue' => 1_000_000]);
    Lead::factory()->create(['status' => 'in_process', 'estimated_revenue' => 2_000_000]);
    Lead::factory()->create(['status' => 'closed_lost', 'estimated_revenue' => 8_000_000]);

    $data = leadReport();

    expect($data->pipelineValue)->toBe(3_000_000.0)
        // Average expected value is per contacted lead: only the 2M row counts.
        ->and($data->averageDealSize)->toBe(2_000_000.0);
});

it('counts disqualified leads as the only judged outcome', function () {
    Lead::factory()->count(5)->create(['status' => 'new']);
    Lead::factory()->count(3)->create(['status' => 'in_process']);
    Lead::factory()->create(['status' => 'closed_lost']);

    $data = leadReport();

    expect($data->disqualifiedLeads)->toBe(1)
        ->and($data->openLeads)->toBe(8);
});

it('counts unassessed leads and buckets them under Not assessed', function () {
    Lead::factory()->count(2)->create(['confidence_level' => null]);
    Lead::factory()->create(['confidence_level' => 'high']);

    $data = leadReport();

    expect($data->unassessedLeads)->toBe(2)
        ->and($data->leadsByConfidence->firstWhere('band', 'none')['label'])->toBe('Not assessed')
        ->and($data->leadsByConfidence->firstWhere('band', 'none')['count'])->toBe(2)
        ->and($data->leadsByConfidence->firstWhere('band', 'high')['count'])->toBe(1)
        ->and($data->leadsByConfidence)->toHaveCount(6);
});

it('filters by confidence, including the not-assessed bucket', function () {
    Lead::factory()->create(['confidence_level' => null]);
    Lead::factory()->create(['confidence_level' => 'high']);

    $assessed = app(PipelineReportService::class)->generate(ReportFilterData::fromArray([
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
        'lead_confidence' => 'high',
    ]));

    $unassessed = app(PipelineReportService::class)->generate(ReportFilterData::fromArray([
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
        'lead_confidence' => 'none',
    ]));

    expect($assessed->totalLeads)->toBe(1)
        ->and($unassessed->totalLeads)->toBe(1)
        ->and($unassessed->unassessedLeads)->toBe(1);
});

it('rejects the opportunity-only stages from the status filter', function () {
    Lead::factory()->create(['status' => 'new']);

    $data = app(PipelineReportService::class)->generate(ReportFilterData::fromArray([
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
        'lead_status' => 'won',
    ]));

    expect($data->totalLeads)->toBe(0);
});

it('exports lead rows with confidence and estimated revenue', function () {
    $lead = Lead::factory()->create([
        'title' => 'Analyzer rollout',
        'status' => 'in_process',
        'confidence_level' => 'very_high',
        'estimated_revenue' => 750_000_000,
        'expected_closing_date' => now()->addMonth(),
    ]);

    $row = app(PipelineReportService::class)->getExportData($this->filters)->first();

    expect($row['Code'])->toBe($lead->lead_code)
        ->and($row['Status'])->toBe('In Process')
        ->and($row['Confidence'])->toBe('Very High')
        ->and($row['Estimated Revenue'])->toBe(750_000_000.0);
});

it('exports Not assessed for a lead without a confidence level', function () {
    Lead::factory()->create(['confidence_level' => null, 'status' => 'new']);

    $row = app(PipelineReportService::class)->getExportData($this->filters)->first();

    expect($row['Confidence'])->toBe('Not assessed');
});
