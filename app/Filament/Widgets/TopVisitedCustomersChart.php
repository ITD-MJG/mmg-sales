<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\ActivityScopeService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class TopVisitedCustomersChart extends ChartWidget
{
    protected ?string $heading = 'Top Visited Customers';

    protected static bool $isLazy = false;

    protected static ?string $height = '320px';

    protected static ?int $sort = 25;

    public static function canView(): bool
    {
        return auth()->check();
    }

    protected function getData(): array
    {
        /** @var User $user */
        $user = Auth::user();
        $service = app(ActivityScopeService::class);

        $rows = $service->getActivityQuery($user)
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, COUNT(*) as activity_count')
            ->groupBy('customer_id')
            ->orderByDesc('activity_count')
            ->orderBy('customer_id')
            ->limit(10)
            // withTrashed(): a deleted customer still owns its historical
            // activities, and without this the bar would render unlabelled.
            ->with(['customer' => fn ($query) => $query->withTrashed()->select('id', 'name')])
            ->get();

        // No ->reverse(): with indexAxis 'y', Chart.js draws index 0 at the top,
        // so the descending order from the query is already highest-first.
        $labels = $rows
            ->map(fn ($row): string => trim($row->customer?->name ?? 'Customer #'.$row->customer_id))
            ->all();

        $values = $rows
            ->map(fn ($row): int => (int) $row->activity_count)
            ->all();

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
                    'label' => 'Activities',
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
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
                'y' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
            ],
        ];
    }
}
