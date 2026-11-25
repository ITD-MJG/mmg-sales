<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Order;
use App\Models\Target;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The figures the dashboard widgets share.
 *
 * Revenue is attributed through `orders.sales_id`, never `orders.created_by`:
 * orders arrive from a spreadsheet import that one administrator runs, so the
 * creator column credits every order to the importer instead of the rep who
 * sold it. `sales_id` is the column the Reports services join on as well.
 */
class DashboardMetricsService
{
    /**
     * @param  list<int>|null  $userIds  null sees every record
     */
    public function revenue(CarbonInterface $from, CarbonInterface $to, ?array $userIds): float
    {
        return (float) $this->orders($from, $to, $userIds)->sum('total_amount');
    }

    /**
     * Net sales of orders that have at least one real payment recorded, whether
     * partial or full: an order that is being paid off is still invoiced, so
     * waiting for `full` would hide most of the month's billing.
     *
     * @param  list<int>|null  $userIds
     */
    public function invoicedRevenue(CarbonInterface $from, CarbonInterface $to, ?array $userIds): float
    {
        return (float) $this->orders($from, $to, $userIds)
            ->whereHas('paymentStatuses', fn (Builder $query) => $query->whereIn('status', ['partial', 'full']))
            ->sum('total_amount');
    }

    /**
     * @param  list<int>|null  $userIds
     */
    public function orderCount(CarbonInterface $from, CarbonInterface $to, ?array $userIds): int
    {
        return $this->orders($from, $to, $userIds)->count();
    }

    /**
     * Decided leads over the period, by the last status change.
     *
     * @param  list<int>|null  $userIds
     * @return array{won: int, lost: int, rate: float|null} rate is null until something is decided
     */
    public function winRate(CarbonInterface $from, CarbonInterface $to, ?array $userIds): array
    {
        $counts = $this->leads($userIds)
            ->whereIn('status', ['closed_won', 'closed_lost'])
            ->whereBetween('updated_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $won = (int) ($counts['closed_won'] ?? 0);
        $lost = (int) ($counts['closed_lost'] ?? 0);
        $decided = $won + $lost;

        return [
            'won' => $won,
            'lost' => $lost,
            'rate' => $decided > 0 ? ($won / $decided) * 100 : null,
        ];
    }

    /**
     * @param  list<int>|null  $userIds
     */
    public function openPipeline(?array $userIds): float
    {
        return (float) $this->leads($userIds)
            ->whereNotIn('status', ['closed_won', 'closed_lost'])
            ->sum('estimated_revenue');
    }

    /**
     * @param  list<int>|null  $userIds
     */
    public function openLeadCount(?array $userIds): int
    {
        return $this->leads($userIds)
            ->whereNotIn('status', ['closed_won', 'closed_lost'])
            ->count();
    }

    /**
     * Revenue per rep, highest first, for the leaderboard.
     *
     * @param  list<int>|null  $userIds
     * @return Collection<int, array{user_id: int, name: string, revenue: float, orders: int}>
     */
    public function revenueByRep(CarbonInterface $from, CarbonInterface $to, ?array $userIds, int $limit = 10): Collection
    {
        $query = Order::query()
            ->join('users', fn ($join) => $join
                ->on('orders.sales_id', '=', 'users.id')
                ->where('users.is_active', true))
            ->whereBetween('orders.order_date', [$from->copy()->toDateString(), $to->copy()->toDateString()])
            ->selectRaw('users.id as user_id, users.name as user_name, SUM(orders.total_amount) as revenue, COUNT(*) as orders')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('revenue')
            ->limit($limit);

        if ($userIds !== null) {
            $query->whereIn('orders.sales_id', $userIds);
        }

        return $query->get()->map(fn ($row): array => [
            'user_id' => (int) $row->user_id,
            'name' => (string) $row->user_name,
            'revenue' => (float) $row->revenue,
            'orders' => (int) $row->orders,
        ]);
    }

    /**
     * Target rows are per rep per month, so a rolling window is matched on the
     * year-month pairs themselves rather than on `year`, which would pull in
     * twelve months of a year that only overlaps the window by one month.
     *
     * @param  list<CarbonInterface>  $months
     * @param  list<int>|null  $userIds
     * @return Collection<int, float> keyed by user id
     */
    public function targetTotalsByUser(array $months, ?array $userIds = null): Collection
    {
        return $this->targetRows($months, $userIds)
            ->groupBy('user_id')
            ->map(fn (Collection $rows): float => (float) $rows->sum('monthly_target'));
    }

    /**
     * One target row per month for a single rep, with absent months present as
     * zero so a chart can plot a continuous axis.
     *
     * @param  list<CarbonInterface>  $months
     * @return array<string, float> keyed by "Y-n"
     */
    public function monthlyTargets(array $months, int $userId): array
    {
        $rows = $this->targetRows($months, [$userId])
            ->keyBy(fn (Target $target): string => $target->year.'-'.$target->month);

        $targets = [];

        foreach ($months as $month) {
            $key = $month->format('Y').'-'.$month->format('n');
            $targets[$key] = (float) ($rows[$key]->monthly_target ?? 0);
        }

        return $targets;
    }

    /**
     * A contiguous window of months ending with the current one, oldest first.
     *
     * @return list<Carbon>
     */
    public function recentMonths(int $count = 12): array
    {
        return collect(range($count - 1, 0))
            ->map(fn (int $offset): Carbon => now()->startOfMonth()->subMonths($offset))
            ->all();
    }

    /**
     * Target rows inside a rolling window. A row is held per rep per month, so
     * the window is matched on year-month pairs; matching on `year` alone would
     * pull in a whole calendar year for a window that only touches one month of
     * it.
     *
     * @param  list<CarbonInterface>  $months
     * @param  list<int>|null  $userIds
     * @return Collection<int, Target>
     */
    private function targetRows(array $months, ?array $userIds): Collection
    {
        $keys = collect($months)
            ->map(fn (CarbonInterface $month): string => $month->format('Y').'-'.$month->format('n'))
            ->all();

        $query = Target::query()
            ->whereIn('year', collect($months)->map(fn (CarbonInterface $month): int => (int) $month->format('Y'))->unique()->all());

        if ($userIds !== null) {
            $query->whereIn('user_id', $userIds);
        }

        return $query
            ->get(['user_id', 'year', 'month', 'monthly_target'])
            ->filter(fn (Target $target): bool => in_array($target->year.'-'.$target->month, $keys, true))
            ->values();
    }

    /**
     * @param  list<int>|null  $userIds
     */
    private function orders(CarbonInterface $from, CarbonInterface $to, ?array $userIds): Builder
    {
        $query = Order::query()
            ->whereBetween('order_date', [$from->copy()->toDateString(), $to->copy()->toDateString()]);

        if ($userIds !== null) {
            $query->whereIn('sales_id', $userIds);
        }

        return PrincipalScopeService::orders($query, auth()->user());
    }

    /**
     * Mirrors the lead half of the visibility contract: a lead belongs to its
     * creator and to every collaborator it was handed to.
     *
     * @param  list<int>|null  $userIds
     */
    private function leads(?array $userIds): Builder
    {
        $query = Lead::query();

        if ($userIds !== null) {
            $query->where(function (Builder $scoped) use ($userIds): void {
                $scoped->whereIn('created_by', $userIds)
                    ->orWhereHas('collaborators', fn (Builder $collaborators): Builder => $collaborators->whereIn('users.id', $userIds));
            });
        }

        return PrincipalScopeService::leads($query, auth()->user());
    }
}
