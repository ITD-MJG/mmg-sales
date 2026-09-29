<?php

namespace App\Filament\Widgets;

use App\Models\Opportunity;
use Filament\Widgets\ChartWidget;

class OpportunityRevenueComparisonChart extends ChartWidget
{
    protected ?string $heading = 'Estimated Revenue vs Actual Sales';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public ?Opportunity $record = null;

    public static function canView(): bool
    {
        return true;
    }

    protected function getData(): array
    {
        if (! $this->record) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $estimated = (float) ($this->record->estimated_revenue ?? 0);
        $actual = (float) $this->record->orders->sum('total_amount');

        return [
            'datasets' => [
                [
                    'label' => 'Estimated Revenue',
                    'data' => [$estimated],
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Actual Sales',
                    'data' => [$actual],
                    'backgroundColor' => $actual >= $estimated ? 'rgba(34, 197, 94, 0.8)' : 'rgba(234, 179, 8, 0.8)',
                    'borderColor' => $actual >= $estimated ? 'rgb(34, 197, 94)' : 'rgb(234, 179, 8)',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => ['Revenue'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
