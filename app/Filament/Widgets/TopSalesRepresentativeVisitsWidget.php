<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\ActivityScopeService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class TopSalesRepresentativeVisitsWidget extends ChartWidget
{
    protected ?string $heading = 'Top Sales Representatives by Customer Visits';

    protected static bool $isLazy = false;

    protected static ?string $height = '280px';

    protected static ?int $sort = 40;

    public static function canView(): bool
    {
        return auth()->check();
    }

    protected function getData(): array
    {
        /** @var User $user */
        $user = Auth::user();
        $service = app(ActivityScopeService::class);

        // Counted per rep, not per rep-and-customer: the rep/customer pair runs
        // to 82 rows, which is a table, not a chart.
        $rows = $service->getActivityQuery($user)
            ->whereNotNull('customer_id')
            ->selectRaw('user_id, COUNT(*) as visit_count')
            ->groupBy('user_id')
            ->orderByDesc('visit_count')
            ->orderBy('user_id')
            ->limit(10)
            ->with(['user:id,name'])
            ->get();

        $labels = $rows
            ->map(fn ($row): string => $row->user?->name ?? 'User #'.$row->user_id)
            ->all();

        $values = $rows
            ->map(fn ($row): int => (int) $row->visit_count)
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
                    'label' => 'Customer Visits',
                    'data' => $values,
                    'backgroundColor' => array_slice($colors, 0, count($values)),
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * A ranking of reps by visit count: horizontal bars, highest first.
     */
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
