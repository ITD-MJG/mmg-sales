<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Opportunities\OpportunityResource;
use App\Models\User;
use App\Services\ActivityScopeService;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class TopVisitedCustomersChart extends ChartWidget
{
    protected ?string $heading = 'Top Visited Leads';

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

        // Rank the visited leads themselves and label each bar with the lead
        // code. An activity belongs to exactly one of a lead or an opportunity
        // (the model enforces it), and the pipeline codes live on whichever one
        // it is, so resolve the code across both.
        $codeExpression = 'COALESCE(leads.lead_code, opportunities.opportunity_code)';

        $rows = $service->getActivityQuery($user)
            ->leftJoin('opportunities', 'activities.opportunity_id', '=', 'opportunities.id')
            ->leftJoin('leads', 'activities.lead_id', '=', 'leads.id')
            ->where(function ($query): void {
                $query->whereNotNull('activities.lead_id')
                    ->orWhereNotNull('activities.opportunity_id');
            })
            ->selectRaw("{$codeExpression} as lead_code, MIN(activities.lead_id) as lead_id, MIN(activities.opportunity_id) as opportunity_id, COUNT(*) as activity_count")
            ->groupByRaw($codeExpression)
            ->orderByDesc('activity_count')
            ->orderBy('lead_code')
            ->limit(10)
            ->get();

        // No ->reverse(): with indexAxis 'y', Chart.js draws index 0 at the top,
        // so the descending order from the query is already highest-first.
        $labels = $rows
            ->map(fn ($row): string => trim((string) $row->lead_code) ?: 'Unknown lead')
            ->all();

        $values = $rows
            ->map(fn ($row): int => (int) $row->activity_count)
            ->all();

        $urls = $rows
            ->map(fn ($row): ?string => $this->recordUrl($row))
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
                    'urls' => $urls,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array|RawJs
    {
        return RawJs::make(<<<'JS'
            {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                },
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                    },
                    y: {
                        grid: { display: false },
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

    /**
     * The bar's lead code is drawn from a lead or an opportunity, so link to
     * whichever record the activity actually points at. The opportunities UI is
     * locked to Super Admin, so a non-admin gets no link rather than a 403.
     */
    private function recordUrl(object $row): ?string
    {
        if ($row->lead_id) {
            return LeadResource::getUrl('view', ['record' => $row->lead_id]);
        }

        if ($row->opportunity_id && OpportunityResource::canAccess()) {
            return OpportunityResource::getUrl('view', ['record' => $row->opportunity_id]);
        }

        return null;
    }
}
