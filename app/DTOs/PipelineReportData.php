<?php

namespace App\DTOs;

use Illuminate\Support\Collection;

readonly class PipelineReportData
{
    public function __construct(
        public float $totalPipelineValue,
        public float $weightedPipelineValue,
        public float $wonValue,
        public float $lostValue,
        public int $totalProjects,
        public int $wonProjects,
        public int $lostProjects,
        public float $winRate,
        public float $averageDealSize,
        public int $averageSalesCycle,
        public Collection $pipelineByStatus,
        public Collection $pipelineBySalesRep,
        public Collection $monthlyTrend,
        public Collection $recentWins,
        public Collection $recentLosses,
    ) {}
}
