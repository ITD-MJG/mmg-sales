<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\ActivityScopeService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TopVisitedLeadsChart extends ChartWidget
{
    protected ?string $heading = 'Top Visits by Lead';

    protected static bool $isLazy = false;

    protected static ?string $height = '200px';

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

        $data = $service->getActivityQuery($user)
            ->whereNotNull('lead_id')
            ->selectRaw('lead_id, COUNT(*) as visit_count')
            ->groupBy('lead_id')
            ->orderByDesc('visit_count')
            ->limit(10)
            ->with(['lead:id,title'])
            ->get();

        $labels = $data
            ->map(fn ($row): string => Str::limit($row->lead?->title ?? 'Lead #'.$row->lead_id, 30))
            ->all();

        $values = $data
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
                    'label' => 'Visits',
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
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }
}
