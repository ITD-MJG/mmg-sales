<?php

namespace App\DTOs;

use Illuminate\Support\Collection;

readonly class SalesStatisticsData
{
    /**
     * @param  Collection<int, array{name: string, orders: int, revenue: float, lead_count: int, lead_value: float, activities: int}>  $teamRows
     */
    public function __construct(
        public ?string $managerName,
        public int $teamSize,
        public int $totalOrders,
        public float $totalRevenue,
        public int $totalLeads,
        public float $totalLeadValue,
        public int $totalActivities,
        public float $averageOrderValue,
        public Collection $monthlyTrend,
        public Collection $teamRows,
    ) {}
}
