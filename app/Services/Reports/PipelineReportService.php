<?php

namespace App\Services\Reports;

use App\DTOs\PipelineReportData;
use App\DTOs\ReportFilterData;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Lead Report.
 *
 * Reports on the thin `leads` table — raw intake and its qualification — not
 * on opportunities. Leads carry no pipeline stages (won/lost belong to
 * opportunities); their enum stops at new/contacted/converted/disqualified.
 *
 * Money here is `estimated_revenue`, the lead's own expectation. Actual
 * revenue is not a lead concept: it only exists once an order is raised.
 */
class PipelineReportService
{
    private const OPEN_STATUSES = ['new', 'contacted'];

    private const CLOSED_STATUSES = ['converted', 'disqualified'];

    public function generate(ReportFilterData $filters): PipelineReportData
    {
        return Cache::remember(
            "lead_report_{$filters->toCacheKey()}",
            now()->addMinutes(5),
            fn () => $this->calculateReport($filters)
        );
    }

    private function calculateReport(ReportFilterData $filters): PipelineReportData
    {
        $primaryQuery = $this->buildBaseQuery($filters);

        $totalLeads = (clone $primaryQuery)->count();
        $openLeads = (clone $primaryQuery)->whereIn('status', self::OPEN_STATUSES)->count();
        $convertedLeads = (clone $primaryQuery)->where('status', 'converted')->count();
        $disqualifiedLeads = (clone $primaryQuery)->where('status', 'disqualified')->count();

        $pipelineValue = (float) (clone $primaryQuery)
            ->whereIn('status', self::OPEN_STATUSES)
            ->sum('estimated_revenue');

        $convertedValue = (float) (clone $primaryQuery)
            ->where('status', 'converted')
            ->sum('estimated_revenue');

        // Win rate over judged leads only: a lead still in intake is neither a
        // win nor a loss, so counting it in the denominator understates the rate.
        $judgedLeads = $convertedLeads + $disqualifiedLeads;
        $winRate = $judgedLeads > 0 ? ($convertedLeads / $judgedLeads) * 100 : 0.0;

        $averageDealSize = $convertedLeads > 0 ? $convertedValue / $convertedLeads : 0.0;

        $unassessedLeads = (clone $primaryQuery)->whereNull('confidence_level')->count();

        return new PipelineReportData(
            totalLeads: $totalLeads,
            openLeads: $openLeads,
            convertedLeads: $convertedLeads,
            disqualifiedLeads: $disqualifiedLeads,
            pipelineValue: $pipelineValue,
            convertedValue: $convertedValue,
            winRate: $winRate,
            averageDealSize: $averageDealSize,
            unassessedLeads: $unassessedLeads,
            leadsByStatus: $this->getLeadsByStatus($filters),
            leadsByConfidence: $this->getLeadsByConfidence($filters),
            leadsBySalesRep: $this->getLeadsBySalesRep($filters),
            monthlyTrend: $this->getMonthlyTrend($filters),
        );
    }

    private function buildBaseQuery(ReportFilterData $filters): Builder
    {
        return Lead::query()
            ->whereBetween('leads.created_at', [$filters->startDate, $filters->endDate])
            ->when($filters->userId, fn (Builder $query, int $userId): Builder => $query->whereHas(
                'collaborators',
                fn (Builder $collaborators): Builder => $collaborators->where('users.id', $userId),
            ))
            ->when(! empty($filters->userIds), fn (Builder $query): Builder => $query->whereHas(
                'collaborators',
                fn (Builder $collaborators): Builder => $collaborators->whereIn('users.id', $filters->userIds),
            ))
            ->when($filters->customerId, fn (Builder $query, int $customerId): Builder => $query->where('leads.customer_id', $customerId))
            ->when($filters->leadStatus, fn (Builder $query, string $status): Builder => $query->where('leads.status', $status))
            ->when($filters->leadSource, fn (Builder $query, string $source): Builder => $query->where('leads.source', $source))
            ->when($filters->leadPriority, fn (Builder $query, string $priority): Builder => $query->where('leads.priority', $priority))
            ->when(
                $filters->leadConfidence === 'none',
                fn (Builder $query): Builder => $query->whereNull('leads.confidence_level'),
            )
            ->when(
                filled($filters->leadConfidence) && $filters->leadConfidence !== 'none',
                fn (Builder $query): Builder => $query->where('leads.confidence_level', $filters->leadConfidence),
            );
    }

    private function getLeadsByStatus(ReportFilterData $filters): Collection
    {
        $counts = $this->buildBaseQuery($filters)
            ->selectRaw('leads.status as status, COUNT(*) as count, COALESCE(SUM(leads.estimated_revenue), 0) as value')
            ->groupBy('leads.status')
            ->get()
            ->keyBy('status');

        // Emit every status in enum order so a status with no rows still shows
        // as an explicit zero rather than vanishing from the chart.
        return collect(['new', 'contacted', 'converted', 'disqualified'])
            ->map(function (string $status) use ($counts): array {
                $row = $counts->get($status);

                return [
                    'status' => $status,
                    'label' => Lead::STATUS_LABELS[$status] ?? ucfirst($status),
                    'count' => (int) ($row->count ?? 0),
                    'value' => (float) ($row->value ?? 0),
                ];
            });
    }

    private function getLeadsByConfidence(ReportFilterData $filters): Collection
    {
        $counts = $this->buildBaseQuery($filters)
            ->selectRaw('leads.confidence_level as band, COUNT(*) as count, COALESCE(SUM(leads.estimated_revenue), 0) as value')
            ->groupBy('leads.confidence_level')
            ->get()
            ->keyBy(fn ($row): string => $row->band ?? 'none');

        $bands = collect(Lead::CONFIDENCE_LABELS)
            ->map(fn (string $label, string $band): array => [
                'band' => $band,
                'label' => $label,
                'count' => (int) ($counts->get($band)->count ?? 0),
                'value' => (float) ($counts->get($band)->value ?? 0),
            ]);

        return $bands->push([
            'band' => 'none',
            'label' => 'Not assessed',
            'count' => (int) ($counts->get('none')->count ?? 0),
            'value' => (float) ($counts->get('none')->value ?? 0),
        ]);
    }

    private function getLeadsBySalesRep(ReportFilterData $filters): Collection
    {
        return $this->buildBaseQuery($filters)
            ->join('lead_collaborators', 'leads.id', '=', 'lead_collaborators.lead_id')
            ->join('users as collaborators', 'lead_collaborators.user_id', '=', 'collaborators.id')
            ->leftJoin('positions', 'collaborators.position_id', '=', 'positions.id')
            ->leftJoin('departments', 'collaborators.department_id', '=', 'departments.id')
            ->where(fn ($query) => $query
                ->whereNull('positions.name')
                ->orWhere('positions.name', 'not like', '%Director%'))
            ->where(fn ($query) => $query
                ->whereNull('departments.name')
                ->orWhere('departments.name', '!=', 'Marketing'))
            ->selectRaw('
                collaborators.id as user_id,
                collaborators.name as name,
                COUNT(DISTINCT leads.id) as count,
                COALESCE(SUM(leads.estimated_revenue), 0) as value,
                SUM(CASE WHEN leads.confidence_level IS NULL THEN 1 ELSE 0 END) as unassessed
            ')
            ->groupBy('collaborators.id', 'collaborators.name')
            ->orderByDesc('value')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'user_id' => $row->user_id,
                'name' => $row->name,
                'count' => (int) $row->count,
                'value' => (float) $row->value,
                'unassessed' => (int) $row->unassessed,
                'creator_name' => null,
            ]);
    }

    private function getMonthlyTrend(ReportFilterData $filters): Collection
    {
        return $this->buildBaseQuery($filters)
            ->selectRaw('YEAR(leads.created_at) as year, MONTH(leads.created_at) as month, COUNT(*) as count, COALESCE(SUM(leads.estimated_revenue), 0) as value')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(fn ($row): array => [
                'period' => Carbon::create($row->year, $row->month)->format('M Y'),
                'count' => (int) $row->count,
                'value' => (float) $row->value,
            ]);
    }

    public function getExportData(ReportFilterData $filters): Collection
    {
        return $this->buildBaseQuery($filters)
            ->with(['customer:id,name', 'creator:id,name'])
            ->orderByDesc('leads.created_at')
            ->get()
            ->map(fn (Lead $lead): array => [
                'Code' => $lead->lead_code,
                'Title' => $lead->title,
                'Customer' => $lead->customer?->name ?? $lead->customer_name,
                'Sales Rep' => $lead->collaborators->first()?->name,
                'Status' => Lead::STATUS_LABELS[$lead->status] ?? ucfirst((string) $lead->status),
                'Confidence' => $lead->confidence_level === null
                    ? 'Not assessed'
                    : (Lead::CONFIDENCE_LABELS[$lead->confidence_level] ?? $lead->confidence_level),
                'Estimated Revenue' => (float) $lead->estimated_revenue,
                'Expected Closing' => $lead->expected_closing_date?->format('d M Y'),
                'Created Date' => $lead->created_at?->format('d M Y'),
                'Converted Date' => $lead->converted_at?->format('d M Y'),
                'Disqualified Date' => $lead->disqualified_at?->format('d M Y'),
            ]);
    }
}
