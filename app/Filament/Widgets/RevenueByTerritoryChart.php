<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\HasVisibilityScope;
use App\Models\Order;
use Filament\Widgets\ChartWidget;

class RevenueByTerritoryChart extends ChartWidget
{
    use HasVisibilityScope;

    protected ?string $heading = 'Revenue by Territory';

    protected static bool $isLazy = false;

    protected static ?string $height = '200px';

    protected static ?int $sort = 40;

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('Super Admin') || $user->hasPermissionTo('view_sales_reports');
    }

    protected function getData(): array
    {
        $user = auth()->user();

        $baseQuery = Order::query();

        self::applyVisibilityScope($baseQuery, 'created_by');

        $data = (clone $baseQuery)
            ->where('order_date', '>=', now()->subMonths(11)->startOfMonth())
            ->join('users', 'orders.created_by', '=', 'users.id')
            ->join('territories', 'users.territory_id', '=', 'territories.id')
            ->selectRaw('territories.name as territory_name, SUM(orders.total_amount) as total')
            ->groupBy('territories.name')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'territory_name')
            ->toArray();

        $labels = array_keys($data);
        $values = array_map(fn ($v) => round((float) $v, 2), array_values($data));

        $colors = [
            'rgb(59, 130, 246)',
            'rgb(34, 197, 94)',
            'rgb(234, 179, 8)',
            'rgb(239, 68, 68)',
            'rgb(168, 85, 247)',
            'rgb(14, 165, 233)',
            'rgb(249, 115, 22)',
            'rgb(107, 114, 128)',
            'rgb(156, 163, 175)',
            'rgb(99, 102, 241)',
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (IDR)',
                    'data' => $values,
                    'backgroundColor' => array_slice($colors, 0, count($values)),
                    'borderWidth' => 0,
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
                'legend' => [
                    'display' => false,
                ],
            ],
            'indexAxis' => 'y',
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}
