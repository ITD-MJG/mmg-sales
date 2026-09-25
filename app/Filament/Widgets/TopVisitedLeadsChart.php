<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\ActivityScopeService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TopVisitedLeadsChart extends ChartWidget
{
    protected ?string $heading = 'Top Leads by Activity';

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
            ->whereNotNull('lead_id')
            ->selectRaw('lead_id, COUNT(*) as activity_count')
            ->groupBy('lead_id')
            ->orderByDesc('activity_count')
            ->orderBy('lead_id')
            ->limit(10)
            ->with(['lead:id,lead_code,title'])
            ->get();

        // No ->reverse(): with indexAxis 'y', Chart.js draws index 0 at the top,
        // so the descending order from the query is already highest-first.

        // lead_code keeps the label unique: leads share titles (several are
        // called "STI"), and a bar chart with duplicate labels is unreadable.
        $labels = $rows
            ->map(fn ($row): string => $row->lead
                ? Str::limit($row->lead->title, 34).' ('.$row->lead->lead_code.')'
                : 'Lead #'.$row->lead_id)
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
