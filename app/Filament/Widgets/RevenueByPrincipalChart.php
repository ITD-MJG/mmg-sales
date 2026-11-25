<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Principals\PrincipalResource;
use App\Filament\Traits\HasVisibilityScope;
use App\Models\Order;
use App\Services\PrincipalScopeService;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class RevenueByPrincipalChart extends ChartWidget
{
    use HasVisibilityScope;

    protected ?string $heading = 'Revenue by Principal';

    protected static bool $isLazy = false;

    protected static ?string $height = '280px';

    protected static ?int $sort = 20;

    // The widget is scoped per viewer in getData(), so the permission gate is no
    // longer what keeps this data private. Kept commented rather than deleted so
    // it can be restored without re-deriving it.
    // public static function canView(): bool
    // {
    //     $user = auth()->user();
    //
    //     if (! $user) {
    //         return false;
    //     }
    //
    //     return $user->hasRole('Super Admin') || $user->hasPermissionTo('view_principal_revenue_widget');
    // }

    protected function getData(): array
    {
        $baseQuery = PrincipalScopeService::orders(Order::query(), auth()->user());

        // Scoped to the viewer's own and subordinate orders. Unattributed
        // orders (sales_id NULL) belong to nobody and are hidden from sales
        // users; global viewers still see them through visibleUserIds().
        self::applyVisibilityScope($baseQuery, 'orders.sales_id');

        $rows = (clone $baseQuery)
            ->where('orders.order_date', '>=', now()->subMonths(11)->startOfMonth())
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('principals', 'order_items.principal_id', '=', 'principals.id')
            ->selectRaw('principals.id as principal_id, principals.name as principal_name, SUM(order_items.subtotal) as total')
            ->groupBy('principals.id', 'principals.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $grandTotal = (float) $rows->sum('total');

        $labels = $rows->map(function ($row) use ($grandTotal): string {
            $share = $grandTotal > 0 ? ((float) $row->total / $grandTotal) * 100 : 0;

            return $row->principal_name.' ('.number_format($share, 1).'%)';
        })->all();

        $values = $rows->map(fn ($row): float => round((float) $row->total, 2))->all();

        $urls = $rows->map(fn ($row): string => PrincipalResource::getUrl('view', ['record' => $row->principal_id]))->all();

        $colors = [
            'rgb(59, 130, 246)',   // blue
            'rgb(34, 197, 94)',    // green
            'rgb(234, 179, 8)',    // yellow
            'rgb(239, 68, 68)',    // red
            'rgb(168, 85, 247)',   // purple
            'rgb(14, 165, 233)',   // sky
            'rgb(249, 115, 22)',   // orange
            'rgb(107, 114, 128)',  // gray
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $values,
                    'backgroundColor' => array_slice($colors, 0, count($values)),
                    'borderWidth' => 0,
                    'urls' => $urls,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * Principals are revenue shares, and there are only a handful, so a
     * doughnut reads the split at a glance. This mirrors the segment widgets
     * under Reports.
     */
    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array|RawJs
    {
        return RawJs::make(<<<'JS'
            {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '55%',
                plugins: {
                    legend: {
                        display: true,
                        position: 'right',
                    },
                },
                onHover: (event, elements) => {
                    event.native.target.style.cursor = elements.length ? 'pointer' : 'default'
                },
                onClick: (event, elements, chart) => {
                    const index = elements[0]?.index
                    const url = index === undefined
                        ? null
                        : chart.data.datasets[0]?.urls?.[index]
                    if (url) window.location.href = url
                },
            }
            JS);
    }
}
