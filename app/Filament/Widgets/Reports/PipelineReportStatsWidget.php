<?php

namespace App\Filament\Widgets\Reports;

use App\DTOs\ReportFilterData;
use App\Services\Reports\PipelineReportService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use NumberFormatter;

class PipelineReportStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 3;

    protected function getStats(): array
    {
        $filters = $this->pageFilters ?? [];

        $filterData = ReportFilterData::fromArray($filters);
        $service = app(PipelineReportService::class);
        $data = $service->generate($filterData);

        $formatter = new NumberFormatter('id_ID', NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 0);

        $statusSummary = $data->leadsByStatus
            ->map(fn (array $row): string => $row['label'].': '.$row['count'])
            ->implode(' | ');

        return [
            Stat::make('Pipeline Value', $formatter->formatCurrency($data->pipelineValue, 'IDR'))
                ->description("{$data->openLeads} leads still in intake")
                ->descriptionIcon('heroicon-m-presentation-chart-line')
                ->color('primary'),

            Stat::make('Total Leads', number_format($data->totalLeads))
                ->description($statusSummary ?: '-'),

            Stat::make('Win Rate', number_format($data->winRate, 1).'%')
                ->description("{$data->convertedLeads} converted of ".($data->convertedLeads + $data->disqualifiedLeads).' judged')
                ->color($data->winRate >= 50 ? 'success' : 'warning'),

            Stat::make('Converted Value', $formatter->formatCurrency($data->convertedValue, 'IDR'))
                ->description("{$data->convertedLeads} leads converted")
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('Average Deal Size', $formatter->formatCurrency($data->averageDealSize, 'IDR'))
                ->description('Converted leads only'),

            Stat::make('Not Assessed', number_format($data->unassessedLeads))
                ->description('Leads with no confidence level')
                ->descriptionIcon('heroicon-m-question-mark-circle')
                ->color($data->unassessedLeads > 0 ? 'warning' : 'success'),
        ];
    }
}
