<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\HasVisibilityScope;
use App\Models\Lead;
use Filament\Widgets\Widget;

/**
 * Status breakdown of the thin `leads` table, as a funnel.
 *
 * The counterpart to OpportunityStatusChart, which is Super Admin only. Staff
 * work out of Leads, so this is the chart they get in that dashboard slot.
 *
 * The thin leads enum stops at new/contacted/converted/disqualified — the
 * pipeline stages (qualified, proposal, negotiation, won, lost) live on the
 * opportunity, not here. Charting those would render permanently empty bands.
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
        'Contacted' => 'contacted',
        'Converted' => 'converted',
        'Disqualified' => 'disqualified',
    ];

    /**
     * One colour per band, in the same order as BANDS.
     *
     * @var list<string>
     */
    private const COLORS = ['#6b7280', '#0ea5e9', '#22c55e', '#ef4444'];

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

        self::applyVisibilityScope($query, 'created_by');

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
