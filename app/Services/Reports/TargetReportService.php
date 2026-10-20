<?php

namespace App\Services\Reports;

use App\DTOs\ReportFilterData;
use App\DTOs\TargetReportData;
use App\Models\Opportunity;
use App\Models\Order;
use App\Models\Target;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TargetReportService
{
    public function generate(ReportFilterData $filters): TargetReportData
    {
        return Cache::remember(
            "target_report_{$filters->toCacheKey()}",
            now()->addMinutes(5),
            fn () => $this->calculateReport($filters)
        );
    }

    private function calculateReport(ReportFilterData $filters): TargetReportData
    {
        $months = $this->monthAxis($filters);

        $targets = $this->targetsByMonth($filters, $months);
        $leadValues = $this->leadValueByMonth($filters);
        $orderValues = $this->orderValueByMonth($filters);

        $monthlyComparison = $months->map(function (array $month) use ($targets, $leadValues, $orderValues): array {
            $key = $month['key'];

            return [
                'period' => $month['label'],
                'year' => $month['year'],
                'month' => $month['month'],
                'target' => (float) ($targets[$key] ?? 0),
                'lead_value' => (float) ($leadValues[$key] ?? 0),
                'order_value' => (float) ($orderValues[$key] ?? 0),
            ];
        });

        return new TargetReportData(
            monthlyComparison: $monthlyComparison,
            totalTarget: (float) $monthlyComparison->sum('target'),
            totalLeadValue: (float) $monthlyComparison->sum('lead_value'),
            totalOrderValue: (float) $monthlyComparison->sum('order_value'),
        );
    }

    /**
     * Continuous month axis across the selected range so gaps render as zero
     * instead of collapsing the x-axis.
     *
     * @return Collection<int, array{key: string, label: string, year: int, month: int}>
     */
    private function monthAxis(ReportFilterData $filters): Collection
    {
        $months = collect();
        $cursor = $filters->startDate->copy()->startOfMonth();
        $last = $filters->endDate->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($last)) {
            $months->push([
                'key' => $cursor->format('Y-m'),
                'label' => $cursor->format('M Y'),
                'year' => (int) $cursor->year,
                'month' => (int) $cursor->month,
            ]);

            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * A month's target is the sum of its `monthly_target` rows. When a year has
     * only an annual row (month is null, per the targets unique key), the
     * annual target is spread evenly across that year's months.
     *
     * @param  Collection<int, array{key: string, label: string, year: int, month: int}>  $months
     * @return array<string, float>
     */
    private function targetsByMonth(ReportFilterData $filters, Collection $months): array
    {
        $years = $months->pluck('year')->unique()->values();

        $query = Target::query()->whereIn('year', $years);

        if ($filters->userId) {
            $query->where('user_id', $filters->userId);
        } elseif (! empty($filters->userIds)) {
            $query->whereIn('user_id', $filters->userIds);
        }

        $rows = $query->get(['year', 'month', 'annual_target', 'monthly_target']);

        $resolved = [];

        foreach ($years as $year) {
            $yearRows = $rows->where('year', $year);

            $monthly = $yearRows->whereNotNull('month')
                ->groupBy('month')
                ->map(fn (Collection $group): float => (float) $group->sum('monthly_target'));

            $annual = (float) $yearRows->whereNull('month')->sum('annual_target');

            foreach ($months->where('year', $year) as $month) {
                $key = $month['key'];

                if ($monthly->has($month['month'])) {
                    $resolved[$key] = $monthly->get($month['month']);
                } elseif ($annual > 0) {
                    $resolved[$key] = round($annual / 12, 2);
                }
            }
        }

        return $resolved;
    }

    /**
     * Lead value is the estimated revenue of the opportunities those leads
     * became — the thin `leads` table carries no monetary column after the
     * 2026-09-26 split.
     *
     * @return array<string, float>
     */
    private function leadValueByMonth(ReportFilterData $filters): array
    {
        return $this->monthlyTotals(
            Opportunity::query()
                ->whereBetween('created_at', [$filters->startDate, $filters->endDate])
                ->when($filters->userId, fn (Builder $q) => $q->where('created_by', $filters->userId))
                ->when(! empty($filters->userIds), fn (Builder $q) => $q->whereIn('created_by', $filters->userIds))
                ->when($filters->customerId, fn (Builder $q) => $q->where('customer_id', $filters->customerId)),
            'created_at',
            'estimated_revenue',
        );
    }

    /**
     * @return array<string, float>
     */
    private function orderValueByMonth(ReportFilterData $filters): array
    {
        return $this->monthlyTotals(
            Order::query()
                ->whereBetween('order_date', [$filters->startDate, $filters->endDate])
                ->when($filters->userId, fn (Builder $q) => $q->where('created_by', $filters->userId))
                ->when(! empty($filters->userIds), fn (Builder $q) => $q->whereIn('created_by', $filters->userIds))
                ->when($filters->departmentId, fn (Builder $q) => $q->where('department_id', $filters->departmentId))
                ->when($filters->principalId, fn (Builder $q) => $q->where('principal_id', $filters->principalId))
                ->when($filters->distributorId, fn (Builder $q) => $q->where('distributor_id', $filters->distributorId))
                ->when($filters->customerId, fn (Builder $q) => $q->where('end_customer_id', $filters->customerId))
                ->when($filters->territoryId, fn (Builder $q) => $q
                    ->leftJoin('users', 'orders.created_by', '=', 'users.id')
                    ->where('users.territory_id', $filters->territoryId)),
            'order_date',
            'total_amount',
        );
    }

    /**
     * @return array<string, float>
     */
    private function monthlyTotals(Builder $query, string $dateColumn, string $valueColumn): array
    {
        $driver = $query->getConnection()->getDriverName();

        $keyExpression = match ($driver) {
            'sqlite' => "strftime('%Y-%m', {$dateColumn})",
            'pgsql' => "to_char({$dateColumn}, 'YYYY-MM')",
            default => "DATE_FORMAT({$dateColumn}, '%Y-%m')",
        };

        return $query
            ->selectRaw("{$keyExpression} as month_key, SUM({$valueColumn}) as total")
            ->groupBy('month_key')
            ->pluck('total', 'month_key')
            ->map(fn ($total): float => (float) $total)
            ->toArray();
    }
}
