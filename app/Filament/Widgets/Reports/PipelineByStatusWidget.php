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
            'prospect' => 'rgb(14, 165, 233)',
            'qualification' => 'rgb(245, 158, 11)',
            'quotation' => 'rgb(139, 92, 246)',
            'convince' => 'rgb(6, 182, 212)',
            'negotiate' => 'rgb(249, 115, 22)',
            'decision_maker' => 'rgb(99, 102, 241)',
            'closed_won' => 'rgb(34, 197, 94)',
            'closed_lost' => 'rgb(239, 68, 68)',
            'hold' => 'rgb(148, 163, 184)',
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
