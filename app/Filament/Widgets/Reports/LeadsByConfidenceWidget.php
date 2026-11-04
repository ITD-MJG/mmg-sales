<?php

namespace App\Filament\Widgets\Reports;

use App\DTOs\ReportFilterData;
use App\Services\Reports\PipelineReportService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class LeadsByConfidenceWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Leads by Confidence';

    public function getData(): array
    {
        $filters = $this->pageFilters ?? [];
        $filterData = ReportFilterData::fromArray($filters);
        $service = app(PipelineReportService::class);
        $data = $service->generate($filterData);

        $rows = $data->leadsByConfidence;

        $colors = [
            'low' => 'rgb(107, 114, 128)',
            'medium' => 'rgb(59, 130, 246)',
            'high' => 'rgb(245, 158, 11)',
            'very_high' => 'rgb(16, 185, 129)',
            'committed' => 'rgb(8, 145, 178)',
            'none' => 'rgb(209, 213, 219)',
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Leads',
                    'data' => $rows->pluck('count')->toArray(),
                    'backgroundColor' => $rows
                        ->map(fn (array $row): string => $colors[$row['band']] ?? 'rgb(107, 114, 128)')
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
