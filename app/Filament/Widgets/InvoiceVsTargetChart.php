<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

/**
 * Twelve months of the rep's own invoiced net sales against the target rows
 * held for the same months. An order counts as invoiced once any payment is
 * recorded, partial included, so being paid off does not defer recognition.
 *
 * Both series are reported in millions to share the axis with the targets.
 */
class InvoiceVsTargetChart extends ChartWidget
{
    protected ?string $heading = 'Invoice vs Target';

    protected int|string|array $columnSpan = 1;

    protected static bool $isLazy = false;

    protected static ?string $height = '280px';

    protected static ?int $sort = 3;

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

        $invoiced = array_map(
            fn ($month): float => $metrics->invoicedRevenue($month, $month->copy()->endOfMonth(), $ownRecords) / 1_000_000,
            $months,
        );

        $targetValues = array_map(
            fn ($month): float => ($monthlyTargets[$month->format('Y').'-'.$month->format('n')] ?? 0) / 1_000_000,
            $months,
        );

        return [
            'datasets' => [
                [
                    'label' => 'Invoice (Rp juta)',
                    'data' => $invoiced,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.75)',
                    'borderColor' => '#10b981',
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
