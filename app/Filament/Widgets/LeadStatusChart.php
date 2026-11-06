<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\HasVisibilityScope;
use App\Models\Lead;
use Filament\Widgets\Widget;

/**
 * Status breakdown of the thin `leads` table, as a funnel.
 *
 * One band per lead status, in pipeline order: New through Closed (Lost).
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
        'New' => 'new',
        'In Process' => 'in_process',
        'Prospecting' => 'prospecting',
        'Qualification' => 'qualification',
        'Proposal' => 'proposal',
        'Negotiation' => 'negotiation',
        'Closed (Won)' => 'closed_won',
        'Closed (Lost)' => 'closed_lost',
    ];

    /**
     * One colour per band, in the same order as BANDS.
     *
     * @var list<string>
     */
    private const COLORS = [
        '#6b7280', // new
        '#0ea5e9', // in_process
        '#38bdf8', // prospecting
        '#f59e0b', // qualification
        '#8b5cf6', // proposal
        '#f97316', // negotiation
        '#22c55e', // closed_won
        '#ef4444', // closed_lost
    ];

    /**
     * Render inline rather than behind a lazy placeholder: the chart is a few
     * hundred bytes of counts, and drawing it needs the canvas in the first
     * paint.
     */
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.lead-status-chart';

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
        $query = Lead::query();

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
        ];
    }
}
