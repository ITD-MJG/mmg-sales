<?php

namespace App\Services\Reports;

use App\DTOs\ReportFilterData;
use App\DTOs\TargetAchievementData;
use App\Models\Opportunity;
use App\Models\Order;
use App\Models\Target;
use App\Services\PrincipalScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TargetAchievementService
{
    public function generate(ReportFilterData $filters): TargetAchievementData
    {
        return Cache::remember(
            "target_achievement_{$filters->toCacheKey()}",
            now()->addMinutes(5),
            fn () => $this->calculate($filters)
        );
    }

    private function calculate(ReportFilterData $filters): TargetAchievementData
    {
        $userId = $filters->userId;

        if (! $userId) {
            return $this->empty();
        }

        $startYear = (int) $filters->startDate->year;
        $endYear = (int) $filters->endDate->year;

        // A target row is per rep per month, so the selected range is a range of
        // months, not years. Matching on `year` alone would return every month of
        // an overlapping year: a Jul-Sep filter would report the full year's total.
        $targets = Target::query()
            ->where('user_id', $userId)
            ->whereBetween('year', [$startYear, $endYear])
            ->where(function ($query) use ($filters, $startYear, $endYear): void {
                if ($startYear === $endYear) {
                    $query->where('year', $startYear)
                        ->whereBetween('month', [$filters->startDate->month, $filters->endDate->month]);

                    return;
                }

                $query
                    ->where(function ($q) use ($filters, $startYear): void {
                        $q->where('year', $startYear)
                            ->where('month', '>=', $filters->startDate->month);
                    })
                    ->orWhere(function ($q) use ($filters, $endYear): void {
                        $q->where('year', $endYear)
                            ->where('month', '<=', $filters->endDate->month);
                    })
                    ->orWhere(function ($q) use ($startYear, $endYear): void {
                        $q->where('year', '>', $startYear)
                            ->where('year', '<', $endYear);
                    });
            })
            ->get();

        // Both figures measure the selected period: the target rows are already
        // limited to its months, so they are the same sum by construction.
        $annualTarget = (float) $targets->sum('monthly_target');
        $monthlyTarget = (float) $targets->sum('monthly_target');
        $orderQuery = fn (): Builder => PrincipalScopeService::applyToOrders(
            Order::query()
                ->where('created_by', $userId)
                ->whereBetween('order_date', [$filters->startDate, $filters->endDate]),
            $filters->principalIds,
        );

        $orderRevenue = (float) $orderQuery()->sum('total_amount');
        $totalOrders = $orderQuery()->count();

        $leadQuery = PrincipalScopeService::applyToOpportunities(
            Opportunity::query()
                ->where('created_by', $userId)
                ->whereBetween('created_at', [$filters->startDate, $filters->endDate]),
            $filters->principalIds,
        );

        $leadValue = (float) (clone $leadQuery)->sum('estimated_revenue');
        $totalLeads = (clone $leadQuery)->count();

        return new TargetAchievementData(
            annualTarget: $annualTarget,
            monthlyTarget: $monthlyTarget,
            totalOrderRevenue: $orderRevenue,
            totalLeadValue: $leadValue,
            orderAchievement: $monthlyTarget > 0 ? ($orderRevenue / $monthlyTarget) * 100 : 0,
            leadAchievement: $monthlyTarget > 0 ? ($leadValue / $monthlyTarget) * 100 : 0,
            totalOrders: $totalOrders,
            totalLeads: $totalLeads,
            monthlyBreakdown: $this->getMonthlyBreakdown($filters, $userId),
        );
    }

    private function getMonthlyBreakdown(ReportFilterData $filters, int $userId): Collection
    {
        $targets = Target::query()
            ->where('user_id', $userId)
            ->whereBetween('year', [(int) $filters->startDate->year, (int) $filters->endDate->year])
            ->get()
            ->keyBy(fn (Target $target) => "{$target->year}-{$target->month}");

        $orderTotals = PrincipalScopeService::applyToOrders(
            Order::query()
                ->where('created_by', $userId)
                ->whereBetween('order_date', [$filters->startDate, $filters->endDate]),
            $filters->principalIds,
        )
            ->selectRaw('YEAR(order_date) as year, MONTH(order_date) as month, SUM(total_amount) as total, COUNT(*) as count')
            ->groupBy('year', 'month')
            ->get()
            ->keyBy(fn ($row) => "{$row->year}-{$row->month}");

        $leadTotals = PrincipalScopeService::applyToOpportunities(
            Opportunity::query()
                ->where('created_by', $userId)
                ->whereBetween('created_at', [$filters->startDate, $filters->endDate]),
            $filters->principalIds,
        )
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(estimated_revenue) as total, COUNT(*) as count')
            ->groupBy('year', 'month')
            ->get()
            ->keyBy(fn ($row) => "{$row->year}-{$row->month}");

        $months = [];
        $cursor = $filters->startDate->copy()->startOfMonth();

        while ($cursor->lte($filters->endDate)) {
            $key = "{$cursor->year}-{$cursor->month}";
            $target = $targets->get($key);
            $order = $orderTotals->get($key);
            $lead = $leadTotals->get($key);

            $monthlyTarget = (float) ($target->monthly_target ?? 0);
            $orderTotal = (float) ($order->total ?? 0);
            $leadTotal = (float) ($lead->total ?? 0);

            $months[] = [
                'period' => $cursor->format('M Y'),
                'target' => $monthlyTarget,
                'orders' => $orderTotal,
                'leads' => $leadTotal,
                'order_count' => (int) ($order->count ?? 0),
                'lead_count' => (int) ($lead->count ?? 0),
                'order_achievement' => $monthlyTarget > 0 ? ($orderTotal / $monthlyTarget) * 100 : 0,
                'lead_achievement' => $monthlyTarget > 0 ? ($leadTotal / $monthlyTarget) * 100 : 0,
            ];

            $cursor->addMonth();
        }

        return collect($months);
    }

    private function empty(): TargetAchievementData
    {
        return new TargetAchievementData(
            annualTarget: 0,
            monthlyTarget: 0,
            totalOrderRevenue: 0,
            totalLeadValue: 0,
            orderAchievement: 0,
            leadAchievement: 0,
            totalOrders: 0,
            totalLeads: 0,
            monthlyBreakdown: collect(),
        );
    }
}
