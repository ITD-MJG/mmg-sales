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
            'in_process' => 'rgb(59, 130, 246)',
            'prospecting' => 'rgb(56, 189, 248)',
            'qualification' => 'rgb(245, 158, 11)',
            'proposal' => 'rgb(139, 92, 246)',
            'negotiation' => 'rgb(249, 115, 22)',
            'closed_won' => 'rgb(34, 197, 94)',
            'closed_lost' => 'rgb(239, 68, 68)',
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
