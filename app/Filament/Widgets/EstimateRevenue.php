<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use Filament\Widgets\ChartWidget;

class EstimateRevenue extends ChartWidget
{
    protected ?string $heading = 'Expected Revenue by Closing Month';

    protected int|string|array $columnSpan = 1;

    protected static bool $isLazy = false;

    protected static ?string $height = '280px';

    protected static ?int $sort = 5;

    protected function getData(): array
    {
        $start = now()->startOfMonth();
        $end = $start->copy()->addMonths(11)->endOfMonth();

        $revenueByMonth = Lead::query()
            ->whereBetween('expected_closing_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("DATE_FORMAT(expected_closing_date, '%Y-%m') as month, SUM(estimated_revenue) as revenue")
            ->groupBy('month')
            ->pluck('revenue', 'month');

        $months = collect(range(0, 11))
            ->map(fn (int $offset) => $start->copy()->addMonths($offset));

        return [
            'datasets' => [[
                'label' => 'Expected Revenue (IDR)',
                'data' => $months
                    ->map(fn ($month) => (float) ($revenueByMonth[$month->format('Y-m')] ?? 0))
                    ->all(),
                'backgroundColor' => [
                    '#2563eb', '#16a34a', '#d97706', '#dc2626',
                    '#7c3aed', '#0891b2', '#4f46e5', '#65a30d',
                    '#ea580c', '#db2777', '#0d9488', '#9333ea',
                ],
                'borderWidth' => 0,
            ]],
            'labels' => $months->map(fn ($month) => $month->translatedFormat('M Y'))->all(),
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
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ];
    }
}
