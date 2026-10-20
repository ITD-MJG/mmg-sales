<?php

namespace App\Services\Reports;

use App\DTOs\PipelineReportData;
use App\DTOs\ReportFilterData;
use App\Models\Opportunity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PipelineReportService
{
    public function generate(ReportFilterData $filters): PipelineReportData
    {
        return Cache::remember(
            "pipeline_report_{$filters->toCacheKey()}",
            now()->addMinutes(5),
            fn () => $this->calculateReport($filters)
        );
    }

    private function calculateReport(ReportFilterData $filters): PipelineReportData
    {
        $primaryQuery = $this->buildBaseQuery($filters);

        $totalProjects = (clone $primaryQuery)->count();
        $wonProjects = (clone $primaryQuery)->where('stage', 'won')->count();
        $lostProjects = (clone $primaryQuery)->where('stage', 'lost')->count();

        $totalPipelineValue = (clone $primaryQuery)->sum('estimated_revenue');
        $wonValue = (clone $primaryQuery)->where('stage', 'won')->sum('estimated_revenue');
        $lostValue = (clone $primaryQuery)->where('stage', 'lost')->sum('estimated_revenue');

        $nonWonProjects = (clone $primaryQuery)->where('stage', '!=', 'won')->count();

        $winRate = $nonWonProjects > 0
            ? ($wonProjects / $nonWonProjects) * 100
            : 0;

        $averageDealSize = $wonProjects > 0 ? $wonValue / $wonProjects : 0;
        $averageSalesCycle = $this->calculateAverageSalesCycle($filters);

        return new PipelineReportData(
            totalPipelineValue: $totalPipelineValue,
            wonValue: $wonValue,
            lostValue: $lostValue,
            totalProjects: $totalProjects,
            wonProjects: $wonProjects,
            lostProjects: $lostProjects,
            winRate: $winRate,
            averageDealSize: $averageDealSize,
            averageSalesCycle: $averageSalesCycle,
            pipelineByStatus: $this->getPipelineByStatus($filters),
            pipelineBySalesRep: $this->getPipelineBySalesRep($filters),
            monthlyTrend: $this->getMonthlyTrend($filters),
            recentWins: $this->getRecentWins($filters),
            recentLosses: $this->getRecentLosses($filters),
        );
    }

    private function buildBaseQuery(ReportFilterData $filters): Builder
    {
        $query = Opportunity::query()
            ->whereBetween('opportunities.created_at', [$filters->startDate, $filters->endDate]);

        if ($filters->userId) {
            $query->whereHas('collaborators', fn ($q) => $q->where('user_id', $filters->userId));
        }

        if (! empty($filters->userIds)) {
            $query->whereHas('collaborators', fn ($q) => $q->whereIn('user_id', $filters->userIds));
        }

        if ($filters->customerId) {
            $query->where('customer_id', $filters->customerId);
        }

        if ($filters->leadStatus) {
            $query->where('stage', $filters->leadStatus);
        }

        if ($filters->leadSource) {
            $query->where('source', $filters->leadSource);
        }

        if ($filters->leadPriority) {
            $query->where('priority', $filters->leadPriority);
        }

        return $query;
    }

    private function calculateAverageSalesCycle(ReportFilterData $filters): int
    {
        $wonProjects = $this->buildBaseQuery($filters)
            ->where('stage', 'won')
            ->whereNotNull('closed_at')
            ->get();

        if ($wonProjects->isEmpty()) {
            return 0;
        }

        $totalDays = $wonProjects->sum(fn ($project) => $project->created_at->diffInDays($project->closed_at));

        return (int) ($totalDays / $wonProjects->count());
    }

    private function getPipelineByStatus(ReportFilterData $filters): Collection
    {
        return $this->buildBaseQuery($filters)
            ->selectRaw('stage, COUNT(*) as count, SUM(estimated_revenue) as value')
            ->groupBy('stage')
            ->get()
            ->map(fn ($row) => [
                'status' => ucfirst($row->stage),
                'count' => $row->count,
                'value' => (float) $row->value,
            ]);
    }

    private function getPipelineBySalesRep(ReportFilterData $filters): Collection
    {
        return $this->buildBaseQuery($filters)
            ->join('opportunity_collaborators', 'opportunities.id', '=', 'opportunity_collaborators.opportunity_id')
            ->join('users as collaborators', 'opportunity_collaborators.user_id', '=', 'collaborators.id')
            ->join('positions', 'collaborators.position_id', '=', 'positions.id')
            ->join('departments', 'collaborators.department_id', '=', 'departments.id')
            ->where('positions.name', 'not like', '%Director%')
            ->where('departments.name', '!=', 'Marketing')
            ->leftJoin('users as adders', 'opportunity_collaborators.added_by', '=', 'adders.id')
            ->selectRaw('
                collaborators.id as user_id,
                collaborators.name,
                COUNT(DISTINCT opportunities.id) as count,
                COALESCE(SUM(opportunities.estimated_revenue), 0) as value,
                GROUP_CONCAT(DISTINCT adders.name SEPARATOR ", ") as creator_names
            ')
            ->groupBy('collaborators.id', 'collaborators.name')
            ->orderByDesc('value')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'user_id' => $row->user_id,
                'name' => $row->name,
                'count' => (int) $row->count,
                'value' => (float) $row->value,
                'creator_name' => $row->creator_names,
            ]);
    }

    private function getMonthlyTrend(ReportFilterData $filters): Collection
    {
        return $this->buildBaseQuery($filters)
            ->selectRaw('YEAR(opportunities.created_at) as year, MONTH(opportunities.created_at) as month, COUNT(*) as count, SUM(opportunities.estimated_revenue) as value')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'period' => Carbon::create($row->year, $row->month)->format('M Y'),
                'count' => $row->count,
                'value' => (float) $row->value,
            ]);
    }

    private function getRecentWins(ReportFilterData $filters): Collection
    {
        return Opportunity::query()
            ->where('stage', 'won')
            ->whereNotNull('closed_at')
            ->when($filters->userId, fn ($q) => $q->where('assigned_to', $filters->userId))
            ->when(! empty($filters->userIds), fn ($q) => $q->whereIn('assigned_to', $filters->userIds))
            ->when($filters->leadSource, fn ($q) => $q->where('source', $filters->leadSource))
            ->when($filters->leadPriority, fn ($q) => $q->where('priority', $filters->leadPriority))
            ->with(['customer:id,name', 'assignedUser:id,name'])
            ->orderByDesc('closed_at')
            ->limit(10)
            ->get()
            ->map(fn ($project) => [
                'id' => $project->id,
                'code' => $project->opportunity_code,
                'name' => $project->title,
                'customer' => $project->customer?->name,
                'value' => (float) $project->estimated_revenue,
                'sales_rep' => $project->assignedUser?->name,
                'closed_at' => $project->closed_at?->format('d M Y'),
            ]);
    }

    private function getRecentLosses(ReportFilterData $filters): Collection
    {
        return Opportunity::query()
            ->where('stage', 'lost')
            ->whereNotNull('closed_at')
            ->when($filters->userId, fn ($q) => $q->where('assigned_to', $filters->userId))
            ->when(! empty($filters->userIds), fn ($q) => $q->whereIn('assigned_to', $filters->userIds))
            ->when($filters->leadSource, fn ($q) => $q->where('source', $filters->leadSource))
            ->when($filters->leadPriority, fn ($q) => $q->where('priority', $filters->leadPriority))
            ->with(['customer:id,name', 'assignedUser:id,name'])
            ->orderByDesc('closed_at')
            ->limit(10)
            ->get()
            ->map(fn ($project) => [
                'id' => $project->id,
                'code' => $project->opportunity_code,
                'name' => $project->title,
                'customer' => $project->customer?->name,
                'value' => (float) $project->estimated_revenue,
                'sales_rep' => $project->assignedUser?->name,
                'closed_at' => $project->closed_at?->format('d M Y'),
            ]);
    }

    public function getExportData(ReportFilterData $filters): Collection
    {
        return $this->buildBaseQuery($filters)
            ->with(['customer:id,name', 'assignedUser:id,name'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($project) => [
                'Code' => $project->opportunity_code,
                'Name' => $project->title,
                'Customer' => $project->customer?->name,
                'Sales Rep' => $project->assignedUser?->name,
                'Status' => ucfirst($project->stage),
                'Estimated Value' => $project->estimated_revenue,
                'Confidence Level' => $project->confidence_level,
                'Created Date' => $project->created_at?->format('d M Y'),
                'Closed Date' => $project->closed_at?->format('d M Y'),
            ]);
    }
}
