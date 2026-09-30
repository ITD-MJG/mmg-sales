<?php

namespace App\DTOs;

use Illuminate\Support\Collection;

readonly class TargetReportData
{
    public function __construct(
        /** @var Collection<int, array{period: string, year: int, month: int, target: float, lead_value: float, order_value: float}> */
        public Collection $monthlyComparison,
        public float $totalTarget,
        public float $totalLeadValue,
        public float $totalOrderValue,
    ) {}
}
