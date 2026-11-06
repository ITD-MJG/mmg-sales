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

            Stat::make('Disqualified', number_format($data->disqualifiedLeads))
                ->description('Contacts judged not interested')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($data->disqualifiedLeads > 0 ? 'warning' : 'success'),

            Stat::make('Average Expected Value', $formatter->formatCurrency($data->averageDealSize, 'IDR'))
                ->description('Per contacted lead'),

            Stat::make('Not Assessed', number_format($data->unassessedLeads))
                ->description('Leads with no confidence level')
                ->descriptionIcon('heroicon-m-question-mark-circle')
                ->color($data->unassessedLeads > 0 ? 'warning' : 'success'),
        ];
    }
}
