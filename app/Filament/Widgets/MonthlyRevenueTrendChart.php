<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\HasVisibilityScope;
use App\Models\Order;
use Filament\Widgets\ChartWidget;

class MonthlyRevenueTrendChart extends ChartWidget
{
    use HasVisibilityScope;

    protected ?string $heading = 'Monthly Revenue Trend';

    protected static bool $isLazy = false;

    protected static ?string $height = '280px';

    protected static ?int $sort = 10;

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('Super Admin') || $user->hasPermissionTo('view_monthly_revenue_widget');
    }

    protected function getData(): array
    {
        $user = auth()->user();

        $baseQuery = Order::query();

        self::applyVisibilityScope($baseQuery, 'created_by');

        // Last 12 months
        $months = collect();
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months->push([
                'key' => $date->format('Y-m'),
                'label' => $date->format('M Y'),
            ]);
        }

        $revenue = (clone $baseQuery)
            ->where('order_date', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw("DATE_FORMAT(order_date, '%Y-%m') as month_key, SUM(total_amount) as total")
            ->groupBy('month_key')
            ->pluck('total', 'month_key')
            ->toArray();

        $labels = $months->pluck('label')->toArray();

        // Reported in millions: raw rupiah runs to ten digits and overflows the
        // axis on a three-column dashboard. Filament emits chart options through
        // @js(), which JSON-encodes them, so a JS tick callback would arrive as
        // a string and be ignored. The unit is named on the axis instead.
        $data = $months->pluck('key')
            ->map(fn ($key) => round((float) ($revenue[$key] ?? 0) / 1_000_000, 2))
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (Rp juta)',
                    'data' => $data,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * Revenue over a continuous month axis is a trend, so a line stays correct.
     */
    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Rp juta',
                    ],
                ],
            ],
        ];
    }
}
