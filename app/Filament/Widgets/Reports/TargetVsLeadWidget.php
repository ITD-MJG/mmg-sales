<?php

namespace App\Filament\Widgets\Reports;

use App\DTOs\ReportFilterData;
use App\DTOs\TargetReportData;
use App\Services\Reports\TargetReportService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class TargetVsLeadWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Target vs Lead';

    protected int|string|array $columnSpan = 1;

    public function getData(): array
    {
        $data = $this->reportData();

        $periods = $data->monthlyComparison->pluck('period')->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Target',
                    'data' => $data->monthlyComparison->pluck('target')->toArray(),
                    'backgroundColor' => 'rgba(107, 114, 128, 0.8)',
                    'borderColor' => 'rgb(107, 114, 128)',
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Lead Value',
                    'data' => $data->monthlyComparison->pluck('lead_value')->toArray(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $periods,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }

    private function reportData(): TargetReportData
    {
        $filters = $this->pageFilters ?? [];
        $filterData = ReportFilterData::fromArray($filters);

        return app(TargetReportService::class)->generate($filterData);
    }
}
