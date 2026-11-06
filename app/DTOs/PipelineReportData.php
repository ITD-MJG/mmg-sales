<?php

namespace App\DTOs;

use Illuminate\Support\Collection;

readonly class PipelineReportData
{
    public function __construct(
        public int $totalLeads,
        public int $openLeads,
        public int $disqualifiedLeads,
        public float $pipelineValue,
        public float $averageDealSize,
        public int $unassessedLeads,
        public Collection $leadsByStatus,
        public Collection $leadsByConfidence,
        public Collection $leadsBySalesRep,
        public Collection $monthlyTrend,
    ) {}
}
