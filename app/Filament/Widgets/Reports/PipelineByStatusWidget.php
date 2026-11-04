<?php

namespace App\Filament\Widgets\Reports;

use App\DTOs\ReportFilterData;
use App\Services\Reports\PipelineReportService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class PipelineByStatusWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Leads by Status';

    public function getData(): array
    {
        $filters = $this->pageFilters ?? [];
        $filterData = ReportFilterData::fromArray($filters);
        $service = app(PipelineReportService::class);
        $data = $service->generate($filterData);

        $rows = $data->leadsByStatus;

        $colors = [
            'new' => 'rgb(107, 114, 128)',
            'contacted' => 'rgb(59, 130, 246)',
            'converted' => 'rgb(16, 185, 129)',
            'disqualified' => 'rgb(239, 68, 68)',
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Leads',
                    'data' => $rows->pluck('count')->toArray(),
                    'backgroundColor' => $rows
                        ->map(fn (array $row): string => $colors[$row['status']] ?? 'rgb(107, 114, 128)')
                        ->toArray(),
                ],
            ],
            'labels' => $rows->pluck('label')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
