<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use App\Models\Target;
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

        $months = collect(range(0, 11))
            ->map(fn (int $offset) => $start->copy()->addMonths($offset));
        $revenueByMonth = Lead::query()
            ->whereBetween('expected_closing_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("DATE_FORMAT(expected_closing_date, '%Y-%m') as month, SUM(estimated_revenue) as revenue")
            ->groupBy('month')
            ->pluck('revenue', 'month');

        $targets = Target::query()
            ->whereIn('year', $months->pluck('year')->unique())
            ->get(['year', 'month', 'annual_target', 'monthly_target'])
            ->groupBy('year');

        $targetValues = $months->map(function ($month) use ($targets): float {
            $yearTargets = $targets->get($month->year, collect());
            $monthlyTargets = $yearTargets->whereNotNull('month')->where('month', $month->month);

            if ($monthlyTargets->isNotEmpty()) {
                return (float) $monthlyTargets->sum('monthly_target');
            }

            $annualTarget = (float) $yearTargets->whereNull('month')->sum('annual_target');

            return $annualTarget > 0 ? round($annualTarget / 12, 2) : 0;
        });

        return [
            'datasets' => [
                [
                    'label' => 'Expected Revenue (IDR)',
                    'data' => $months
                        ->map(fn ($month) => (float) ($revenueByMonth[$month->format('Y-m')] ?? 0))
                        ->all(),
                    'backgroundColor' => 'rgba(37, 99, 235, 0.75)',
                    'borderColor' => '#2563eb',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Target (IDR)',
                    'data' => $targetValues->all(),
                    'backgroundColor' => 'rgba(220, 38, 38, 0.65)',
                    'borderColor' => '#dc2626',
                    'borderWidth' => 1,
                ],
            ],
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
                'legend' => ['display' => true, 'position' => 'top'],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ];
    }
}
