<?php

namespace App\Services\Reports;

use App\DTOs\ReportFilterData;
use App\DTOs\SalesStatisticsData;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Per-rep and per-manager figures for the Sales Statistics page.
 *
 * Two modes, both keyed off the page filters:
 *  - a sales rep selected (`user_id`) reports on that rep alone;
 *  - a manager selected (`manager_id`) reports on the manager's whole
 *    reporting line, because a manager is measured on the team, not on the
 *    handful of records they raise themselves.
 */
class SalesStatisticsService
{
    public function generate(ReportFilterData $filters, ?int $managerId = null): SalesStatisticsData
    {
        return Cache::remember(
            'sales_statistics_'.md5(serialize([$filters, $managerId])),
            now()->addMinutes(5),
            fn () => $this->calculate($filters, $managerId),
        );
    }

    private function calculate(ReportFilterData $filters, ?int $managerId): SalesStatisticsData
    {
        if ($managerId) {
            $manager = User::query()->find($managerId);
            $userIds = [$managerId, ...($manager?->subordinateUserIds() ?? [])];

            return $this->build(
                filters: $filters,
                userIds: $userIds,
                managerName: $manager?->name,
                breakdownIds: $manager?->subordinateUserIds() ?? [],
            );
        }

        if (! $filters->userId) {
            return $this->empty();
        }

        return $this->build(
            filters: $filters,
            userIds: [$filters->userId],
            managerName: null,
            breakdownIds: [$filters->userId],
        );
    }

    /**
     * @param  list<int>  $userIds
     * @param  list<int>  $breakdownIds
     */
    private function build(ReportFilterData $filters, array $userIds, ?string $managerName, array $breakdownIds): SalesStatisticsData
    {
        $orders = $this->orderAggregates($filters, $userIds);
        $leads = $this->leadAggregates($filters, $userIds);

        return new SalesStatisticsData(
            managerName: $managerName,
            teamSize: count($breakdownIds),
            totalOrders: (int) $orders->sum('orders'),
            totalRevenue: (float) $orders->sum('revenue'),
            totalLeads: (int) $leads->sum('leads'),
            totalLeadValue: (float) $leads->sum('value'),
            totalActivities: $this->activityCount($filters, $userIds),
            averageOrderValue: (int) $orders->sum('orders') > 0
                ? (float) $orders->sum('revenue') / (int) $orders->sum('orders')
                : 0.0,
            monthlyTrend: $this->monthlyTrend($filters, $userIds),
            teamRows: $this->teamRows($filters, $breakdownIds),
        );
    }

    /**
     * @param  list<int>  $userIds
     * @return Collection<int, array{orders: int, revenue: float, month_key: string}|Collection>
     */
    private function orderAggregates(ReportFilterData $filters, array $userIds): Collection
    {
        return $this->byMonth(
            Order::query()
                ->whereIn('created_by', $userIds)
                ->whereBetween('order_date', [$filters->startDate, $filters->endDate]),
            'order_date',
            'SUM(total_amount) as revenue, COUNT(*) as orders',
        );
    }

    /**
     * @param  list<int>  $userIds
     */
    private function leadAggregates(ReportFilterData $filters, array $userIds): Collection
    {
        return $this->byMonth(
            Lead::query()
                ->whereIn('created_by', $userIds)
                ->whereBetween('created_at', [$filters->startDate, $filters->endDate]),
            'created_at',
            'SUM(estimated_revenue) as value, COUNT(*) as leads',
        );
    }

    /**
     * Groups a query into `Y-m` buckets carrying the requested aggregates.
     * The date key expression is driver-specific, mirroring TargetReportService.
     *
     * @return Collection<string, mixed>
     */
    private function byMonth(Builder $query, string $dateColumn, string $select): Collection
    {
        $driver = $query->getConnection()->getDriverName();

        $keyExpression = match ($driver) {
            'sqlite' => "strftime('%Y-%m', {$dateColumn})",
            'pgsql' => "to_char({$dateColumn}, 'YYYY-MM')",
            default => "DATE_FORMAT({$dateColumn}, '%Y-%m')",
        };

        return $query
            ->selectRaw("{$keyExpression} as month_key, {$select}")
            ->groupBy('month_key')
            ->get()
            ->keyBy('month_key');
    }

    /**
     * @param  list<int>  $userIds
     */
    private function activityCount(ReportFilterData $filters, array $userIds): int
    {
        return Activity::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('performed_at', [$filters->startDate, $filters->endDate])
            ->count();
    }

    /**
     * Continuous month axis so a gap in the data renders as zero rather than
     * collapsing the x-axis.
     *
     * @param  list<int>  $userIds
     * @return Collection<int, array{period: string, revenue: float, orders: int, leads: int}>
     */
    private function monthlyTrend(ReportFilterData $filters, array $userIds): Collection
    {
        $orders = $this->orderAggregates($filters, $userIds);
        $leads = $this->leadAggregates($filters, $userIds);

        $trend = collect();
        $cursor = $filters->startDate->copy()->startOfMonth();
        $last = $filters->endDate->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($last)) {
            $key = $cursor->format('Y-m');

            $trend->push([
                'period' => $cursor->format('M Y'),
                'revenue' => (float) ($orders->get($key)->revenue ?? 0),
                'orders' => (int) ($orders->get($key)->orders ?? 0),
                'leads' => (int) ($leads->get($key)->leads ?? 0),
            ]);

            $cursor->addMonth();
        }

        return $trend;
    }

    /**
     * One row per member of the reporting line, so a manager can see who is
     * actually carrying the team's numbers.
     *
     * @param  list<int>  $userIds
     * @return Collection<int, array{name: string, orders: int, revenue: float, lead_count: int, lead_value: float, activities: int}>
     */
    private function teamRows(ReportFilterData $filters, array $userIds): Collection
    {
        if ($userIds === []) {
            return collect();
        }

        $orders = $this->perUser(
            Order::query()
                ->whereBetween('order_date', [$filters->startDate, $filters->endDate]),
            'created_by',
            'SUM(total_amount) as revenue, COUNT(*) as total',
        );

        $leads = $this->perUser(
            Lead::query()
                ->whereBetween('created_at', [$filters->startDate, $filters->endDate]),
            'created_by',
            'SUM(estimated_revenue) as revenue, COUNT(*) as total',
        );

        $activities = Activity::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('performed_at', [$filters->startDate, $filters->endDate])
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        return User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'name' => $user->name,
                'orders' => (int) ($orders[$user->id]->total ?? 0),
                'revenue' => (float) ($orders[$user->id]->revenue ?? 0),
                'lead_count' => (int) ($leads[$user->id]->total ?? 0),
                'lead_value' => (float) ($leads[$user->id]->revenue ?? 0),
                'activities' => (int) ($activities[$user->id] ?? 0),
            ]);
    }

    /**
     * @return Collection<int|string, mixed>
     */
    private function perUser(Builder $query, string $userColumn, string $select): Collection
    {
        return $query
            ->whereNotNull($userColumn)
            ->selectRaw("{$userColumn} as owner_id, {$select}")
            ->groupBy($userColumn)
            ->get()
            ->keyBy('owner_id');
    }

    private function empty(): SalesStatisticsData
    {
        return new SalesStatisticsData(
            managerName: null,
            teamSize: 0,
            totalOrders: 0,
            totalRevenue: 0.0,
            totalLeads: 0,
            totalLeadValue: 0.0,
            totalActivities: 0,
            averageOrderValue: 0.0,
            monthlyTrend: collect(),
            teamRows: collect(),
        );
    }
}
