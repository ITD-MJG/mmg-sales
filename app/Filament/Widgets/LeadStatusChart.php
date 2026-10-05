<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\HasVisibilityScope;
use App\Models\Lead;
use Leandrocfe\FilamentApexCharts\Enums\ApexChartTypeEnum;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

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
 * A funnel renders in series order, so the top-to-bottom reading is fixed at
 * New → Contacted → Converted → Disqualified and does not depend on the data.
 */
class LeadStatusChart extends ApexChartWidget
{
    use HasVisibilityScope;

    protected static ?string $chartId = 'leadStatusChart';

    protected static ?string $heading = 'Lead Status';

    protected static ?int $contentHeight = 280;

    public static function canView(): bool
    {
        return true;
    }

    protected function getOptions(): array
    {
        $baseQuery = Lead::query();

        self::applyVisibilityScope($baseQuery, 'created_by');

        $statuses = ['new', 'contacted', 'converted', 'disqualified'];

        $counts = (clone $baseQuery)
            ->whereIn('status', $statuses)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $labels = ['New', 'Contacted', 'Converted', 'Disqualified'];
        $values = array_map(fn ($s) => (int) ($counts[$s] ?? 0), $statuses);

        return [
            'chart' => [
                'type' => ApexChartTypeEnum::Funnel->value,
                'height' => 280,
            ],
            // ApexCharts v6 requires series as an array of series objects; a
            // flat [87, 101, 16, 3] leaves series[0].data undefined, so nothing
            // binds and the chart draws an empty plot with no error.
            'series' => [
                [
                    'name' => 'Leads',
                    'data' => $values,
                ],
            ],
            'labels' => $labels,
            'colors' => ['#6b7280', '#0ea5e9', '#22c55e', '#ef4444'],
            'legend' => [
                'show' => true,
                'position' => 'right',
            ],
            'plotOptions' => [
                'funnel' => [
                    // Keep the declared order rather than sorting by value: the
                    // bands must always read New → Contacted → Converted →
                    // Disqualified, even when a later status outnumbers an
                    // earlier one.
                    'sortData' => false,
                ],
                'bar' => [
                    // A funnel is a horizontal bar chart with isFunnel set, and
                    // without `distributed` every band paints with the first
                    // colour and the legend stays empty — the per-band `colors`
                    // and `legend` above would be silently ignored.
                    'distributed' => true,
                ],
            ],
        ];
    }
}
