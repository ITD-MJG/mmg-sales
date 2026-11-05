<?php

namespace App\Filament\Widgets\Reports;

use App\DTOs\ReportFilterData;
use App\Services\Reports\SalesStatisticsService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use NumberFormatter;

class SalesStatisticsStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 4;

    protected function getStats(): array
    {
        $filters = $this->pageFilters ?? [];
        $data = app(SalesStatisticsService::class)->generate(
            ReportFilterData::fromArray($filters),
            $filters['manager_id'] ?? null,
        );

        $formatter = new NumberFormatter('id_ID', NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 0);

        return [
            Stat::make('Total Revenue', $formatter->formatCurrency($data->totalRevenue, 'IDR'))
                ->description("{$data->totalOrders} orders in period"),

            Stat::make('Total Leads', number_format($data->totalLeads))
                ->description($formatter->formatCurrency($data->totalLeadValue, 'IDR').' estimated value'),

            Stat::make('Activities', number_format($data->totalActivities))
                ->description('Logged in period'),

            Stat::make('Average Order Value', $formatter->formatCurrency($data->averageOrderValue, 'IDR'))
                ->description($data->managerName
                    ? "Team of {$data->teamSize} under {$data->managerName}"
                    : 'Per order'),
        ];
    }
}
