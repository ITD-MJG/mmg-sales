<?php

namespace App\Filament\Widgets\Reports;

use App\DTOs\ReportFilterData;
use App\Services\Reports\SalesReportService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use NumberFormatter;

class SalesReportStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $filters = $this->pageFilters ?? [];

        $filterData = ReportFilterData::fromArray($filters);
        $service = app(SalesReportService::class);
        $data = $service->generate($filterData);

        $formatter = new NumberFormatter('id_ID', NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 0);

        return [
            Stat::make('Total Revenue', $formatter->formatCurrency($data->totalRevenue, 'IDR'))
                ->description('Revenue for selected period'),

            Stat::make('Net Sales', $formatter->formatCurrency($data->totalNetSales, 'IDR'))
                ->description('Total net sales after discounts'),

            Stat::make('Total Orders', number_format($data->totalOrders))
                ->description('Orders in selected period'),

            Stat::make('Average Order Value', $formatter->formatCurrency($data->averageOrderValue, 'IDR'))
                ->description('Average per order'),
        ];
    }
}
