<?php

namespace App\Filament\Widgets\Reports;

use App\DTOs\ReportFilterData;
use App\Services\Reports\TargetAchievementService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class TargetVsOrdersWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Target vs Orders';

    protected int|string|array $columnSpan = 1;

    protected static bool $isLazy = false;

    protected function getData(): array
    {
        $filters = $this->pageFilters ?? [];
        $filterData = ReportFilterData::fromArray($filters);
        $service = app(TargetAchievementService::class);
        $data = $service->generate($filterData);

        $labels = $data->monthlyBreakdown->pluck('period')->toArray();
        $targets = $data->monthlyBreakdown->pluck('target')->toArray();
        $orders = $data->monthlyBreakdown->pluck('orders')->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Target',
                    'data' => $targets,
                    'backgroundColor' => 'rgba(148, 163, 184, 0.8)',
                    'borderColor' => 'rgb(148, 163, 184)',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Orders',
                    'data' => $orders,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $labels,
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
                'legend' => ['display' => true, 'position' => 'top'],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ];
    }
}
