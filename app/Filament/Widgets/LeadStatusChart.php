<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Traits\HasVisibilityScope;
use App\Models\Lead;
use App\Services\PrincipalScopeService;
use Filament\Widgets\Widget;

/**
 * Status breakdown of the thin `leads` table, as a funnel.
 *
 * One band per lead status, in pipeline order: New Lead through Hold.
 *
 * Drawn with Chart.js and chartjs-chart-funnel rather than the ApexCharts
 * widget the other dashboard charts use. That plugin plots the dataset in the
 * order given and offers no sort-by-value, which is the behaviour this chart
 * needs; ApexCharts reordered the bands unless explicitly told not to.
 */
class LeadStatusChart extends Widget
{
    use HasVisibilityScope;

    /**
     * The funnel bands, top to bottom, with the lead status each one counts.
     *
     * @var array<string, string>
     */
    private const BANDS = [
        'New Lead' => 'new',
        'Prospect/ Discovery' => 'prospect',
        'Qualification/ Acceptance of Need' => 'qualification',
        'Quotation' => 'quotation',
        'Convince' => 'convince',
        'Negotiate' => 'negotiate',
        'Decision Maker' => 'decision_maker',
        'Win' => 'closed_won',
        'Lost' => 'closed_lost',
        'Hold' => 'hold',
    ];

    /**
     * One colour per band, in the same order as BANDS.
     *
     * @var list<string>
     */
    private const COLORS = [
        '#6b7280', // new
        '#0ea5e9', // prospect
        '#f59e0b', // qualification
        '#8b5cf6', // quotation
        '#06b6d4', // convince
        '#f97316', // negotiate
        '#6366f1', // decision_maker
        '#22c55e', // closed_won
        '#ef4444', // closed_lost
        '#94a3b8', // hold
    ];

    /**
     * Render inline rather than behind a lazy placeholder: the chart is a few
     * hundred bytes of counts, and drawing it needs the canvas in the first
     * paint.
     */
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.lead-status-chart';

    // One column, so the funnel shares the row with Expected Revenue by Closing
    // Month and Monthly Order Value on the three-column dashboard grid.
    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return true;
    }

    /**
     * The labels, values and colours the chart draws.
     *
     * @return array{labels: list<string>, values: list<int>, colors: list<string>}
     */
    public function getChartData(): array
    {
        $query = PrincipalScopeService::leads(Lead::query(), auth()->user());

        // Leads are owned by their creator or any collaborator; the bar counts
        // must agree with what the lead list shows.
        self::applyLeadVisibilityScope($query, 'created_by');

        $counts = $query
            ->whereIn('status', array_values(self::BANDS))
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'labels' => array_keys(self::BANDS),
            'values' => array_map(
                fn ($status) => (int) ($counts[$status] ?? 0),
                array_values(self::BANDS),
            ),
            'colors' => self::COLORS,
            'urls' => array_map(
                fn (string $status): string => LeadResource::getUrl('index', [
                    'filters' => ['status' => ['value' => $status]],
                ]),
                array_values(self::BANDS),
            ),
        ];
    }
}
