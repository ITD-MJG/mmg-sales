<?php

namespace App\DTOs;

use Illuminate\Support\Collection;

readonly class TargetAchievementData
{
    public function __construct(
        public float $annualTarget,
        public float $monthlyTarget,
        public float $totalOrderRevenue,
        public float $totalLeadValue,
        public float $orderAchievement,
        public float $leadAchievement,
        public int $totalOrders,
        public int $totalLeads,
        public Collection $monthlyBreakdown,
    ) {}
}
