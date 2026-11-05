<?php

namespace App\Filament\Widgets\Reports;

use App\DTOs\ReportFilterData;
use App\Services\Reports\SalesStatisticsService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class SalesStatisticsTrendWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Revenue and Leads per Month';

    protected int|string|array $columnSpan = 'full';

    public function getData(): array
    {
        $filters = $this->pageFilters ?? [];
        $data = app(SalesStatisticsService::class)->generate(
            ReportFilterData::fromArray($filters),
            $filters['manager_id'] ?? null,
        );

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $data->monthlyTrend->pluck('revenue')->toArray(),
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                    'yAxisID' => 'revenue',
                ],
                [
                    'label' => 'Leads',
                    'data' => $data->monthlyTrend->pluck('leads')->toArray(),
                    'borderColor' => 'rgb(16, 185, 129)',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => false,
                    'tension' => 0.4,
                    'yAxisID' => 'leads',
                ],
            ],
            'labels' => $data->monthlyTrend->pluck('period')->toArray(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'revenue' => [
                    'position' => 'left',
                ],
                'leads' => [
                    'position' => 'right',
                    'grid' => ['drawOnChartArea' => false],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
