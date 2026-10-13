<?php

namespace App\Filament\Widgets\Reports;

use App\DTOs\ReportFilterData;
use App\Services\Reports\TargetAchievementService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use NumberFormatter;

class TargetAchievementStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 4;

    protected function getStats(): array
    {
        $filters = $this->pageFilters ?? [];
        $filterData = ReportFilterData::fromArray($filters);
        $service = app(TargetAchievementService::class);
        $data = $service->generate($filterData);

        $formatter = new NumberFormatter('id_ID', NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 0);

        $orderColor = $this->achievementColor($data->orderAchievement);
        $leadColor = $this->achievementColor($data->leadAchievement);

        return [
            Stat::make('Target', $formatter->formatCurrency($data->monthlyTarget, 'IDR'))
                ->description('Monthly target total for period'),

            Stat::make('Order Achievement', number_format($data->orderAchievement, 1).'%')
                ->description($formatter->formatCurrency($data->totalOrderRevenue, 'IDR')." across {$data->totalOrders} orders")
                ->color($orderColor),

            Stat::make('Lead Achievement', number_format($data->leadAchievement, 1).'%')
                ->description($formatter->formatCurrency($data->totalLeadValue, 'IDR')." across {$data->totalLeads} leads")
                ->color($leadColor),

            Stat::make('Annual Target', $formatter->formatCurrency($data->annualTarget, 'IDR'))
                ->description('Annual target total for period'),
        ];
    }

    private function achievementColor(float $percentage): string
    {
        if ($percentage >= 100) {
            return 'success';
        }

        if ($percentage >= 75) {
            return 'warning';
        }

        return 'danger';
    }
}
