<?php

namespace App\Filament\Widgets\Reports;

use App\DTOs\ReportFilterData;
use App\DTOs\TargetReportData;
use App\Services\Reports\TargetReportService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class TargetVsOrderWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Target vs Order';

    protected int|string|array $columnSpan = 'full';

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
                    'label' => 'Order Value',
                    'data' => $data->monthlyComparison->pluck('order_value')->toArray(),
                    'backgroundColor' => 'rgba(16, 185, 129, 0.8)',
                    'borderColor' => 'rgb(16, 185, 129)',
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
