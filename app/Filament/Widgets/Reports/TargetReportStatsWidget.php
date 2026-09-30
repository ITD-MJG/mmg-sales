<?php

namespace App\Filament\Widgets\Reports;

use App\DTOs\ReportFilterData;
use App\Services\Reports\TargetReportService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use NumberFormatter;

class TargetReportStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $filters = $this->pageFilters ?? [];

        $filterData = ReportFilterData::fromArray($filters);
        $data = app(TargetReportService::class)->generate($filterData);

        $formatter = new NumberFormatter('id_ID', NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 0);

        $leadAchievement = $data->totalTarget > 0
            ? ($data->totalLeadValue / $data->totalTarget) * 100
            : 0;

        $orderAchievement = $data->totalTarget > 0
            ? ($data->totalOrderValue / $data->totalTarget) * 100
            : 0;

        return [
            Stat::make('Total Target', $formatter->formatCurrency($data->totalTarget, 'IDR'))
                ->description('Target for selected period'),

            Stat::make('Lead Value', $formatter->formatCurrency($data->totalLeadValue, 'IDR'))
                ->description(number_format($leadAchievement, 1).'% of target')
                ->color($this->achievementColor($leadAchievement)),

            Stat::make('Order Value', $formatter->formatCurrency($data->totalOrderValue, 'IDR'))
                ->description(number_format($orderAchievement, 1).'% of target')
                ->color($this->achievementColor($orderAchievement)),
        ];
    }

    private function achievementColor(float $achievement): string
    {
        if ($achievement >= 100) {
            return 'success';
        }

        return $achievement >= 75 ? 'warning' : 'danger';
    }
}
