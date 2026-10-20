<?php

namespace App\Filament\Widgets\Reports;

use App\DTOs\ReportFilterData;
use App\Services\Reports\CustomerReportService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use NumberFormatter;

class CustomerReportStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $filters = $this->pageFilters ?? [];

        $filterData = ReportFilterData::fromArray($filters);
        $service = app(CustomerReportService::class);
        $data = $service->generate($filterData);

        $formatter = new NumberFormatter('id_ID', NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 0);

        return [
            Stat::make('Total Customers', number_format($data->totalCustomers))
                ->description('Active customers'),

            Stat::make('Total Revenue', $formatter->formatCurrency($data->totalRevenue, 'IDR'))
                ->description('Revenue for selected period'),

            Stat::make('New Customers', number_format($data->newCustomers))
                ->description('This period'),

            Stat::make('Avg Revenue per Customer', $formatter->formatCurrency($data->averageRevenuePerCustomer, 'IDR'))
                ->description('Revenue / Customer'),
        ];
    }
}
