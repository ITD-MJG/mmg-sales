<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

/**
 * Twelve months of the rep's own order net sales against the target rows held
 * for the same months, so the gap between sold and promised is a trend rather
 * than a single percentage.
 *
 * Both series are reported in millions: monthly targets reach billions of
 * rupiah and raw values overflow the three-column dashboard axis.
 */
class TargetVsAchievementChart extends ChartWidget
{
    protected ?string $heading = 'Order vs Target';

    protected int|string|array $columnSpan = 1;

    protected static bool $isLazy = false;

    protected static ?string $height = '280px';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $user = Auth::user();

        if (! $user) {
            return ['datasets' => [], 'labels' => []];
        }

        $metrics = app(DashboardMetricsService::class);
        $months = $metrics->recentMonths();
        $ownRecords = [$user->id];

        $monthlyTargets = $metrics->monthlyTargets($months, $user->id);

        $revenue = array_map(
            fn ($month): float => $metrics->revenue($month, $month->copy()->endOfMonth(), $ownRecords) / 1_000_000,
            $months,
        );

        $targetValues = array_map(
            fn ($month): float => ($monthlyTargets[$month->format('Y').'-'.$month->format('n')] ?? 0) / 1_000_000,
            $months,
        );

        return [
            'datasets' => [
                [
                    'label' => 'Order (Rp juta)',
                    'data' => $revenue,
                    'backgroundColor' => 'rgba(37, 99, 235, 0.75)',
                    'borderColor' => '#2563eb',
                    'borderWidth' => 1,
                    'order' => 2,
                ],
                [
                    'label' => 'Target (Rp juta)',
                    'data' => $targetValues,
                    'type' => 'line',
                    'borderColor' => '#dc2626',
                    'backgroundColor' => 'rgba(220, 38, 38, 0.15)',
                    'borderWidth' => 2,
                    'pointRadius' => 2,
                    'fill' => false,
                    'order' => 1,
                ],
            ],
            'labels' => array_map(fn ($month): string => $month->translatedFormat('M Y'), $months),
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
                'y' => [
                    'beginAtZero' => true,
                    'title' => ['display' => true, 'text' => 'Rp juta'],
                ],
            ],
        ];
    }
}
