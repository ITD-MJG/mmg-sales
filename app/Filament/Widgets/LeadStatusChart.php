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
        $series = array_map(fn ($s) => (int) ($counts[$s] ?? 0), $statuses);

        return [
            'chart' => [
                'type' => ApexChartTypeEnum::Funnel->value,
                'height' => 280,
            ],
            // A funnel is ApexCharts' bar variant, and a bar reads its values
            // as `series[i][j]`. A flat array renders nothing here — the
            // values must sit behind a named series.
            'series' => [['name' => 'Leads', 'data' => $series]],
            'labels' => $labels,
            'colors' => ['#6b7280', '#0ea5e9', '#22c55e', '#ef4444'],
            'legend' => [
                'show' => true,
                'position' => 'right',
            ],
            'plotOptions' => [
                // One colour per band instead of the single series colour.
                'bar' => ['distributed' => true],
            ],
            // A bar's legend is named from the x-axis categories, not `labels`.
            'xaxis' => ['categories' => $labels],
        ];
    }
}
