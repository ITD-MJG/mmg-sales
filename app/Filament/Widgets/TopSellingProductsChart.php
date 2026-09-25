<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\HasVisibilityScope;
use App\Models\OrderItem;
use Filament\Widgets\ChartWidget;

class TopSellingProductsChart extends ChartWidget
{
    use HasVisibilityScope;

    protected ?string $heading = 'Top Selling Products';

    protected static bool $isLazy = false;

    protected static ?string $height = '200px';

    protected static ?int $sort = 30;

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('Super Admin') || $user->hasPermissionTo('view_top_selling_products_widget');
    }

    protected function getData(): array
    {
        $user = auth()->user();

        $baseQuery = OrderItem::query();

        // Scope via the parent order's created_by
        self::applyVisibilityScope($baseQuery->join('orders', 'order_items.order_id', '=', 'orders.id'), 'orders.created_by');

        $data = (clone $baseQuery)
            ->where('orders.order_date', '>=', now()->subMonths(11)->startOfMonth())
            ->join('products', 'order_items.item_id', '=', 'products.id')
            ->selectRaw('products.name as product_name, SUM(order_items.quantity) as total_qty')
            ->groupBy('products.name')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->pluck('total_qty', 'product_name')
            ->toArray();

        $labels = array_keys($data);
        $values = array_values($data);

        return [
            'datasets' => [
                [
                    'label' => 'Units Sold',
                    'data' => $values,
                    'backgroundColor' => 'rgb(34, 197, 94)',
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
