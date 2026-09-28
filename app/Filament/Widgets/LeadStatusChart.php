<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\HasVisibilityScope;
use App\Models\Lead;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class LeadStatusChart extends ChartWidget
{
    use HasVisibilityScope;

    protected ?string $heading = 'Lead Status';

    protected static bool $isLazy = false;

    protected static ?string $height = '280px';

    public static function canView(): bool
    {
        return true;
    }

    protected function getData(): array
    {
        $user = auth()->user();

        $baseQuery = Lead::query();

        self::applyVisibilityScope($baseQuery, 'created_by');

        // Global viewers already see every lead: adding this as a top-level OR
        // would collapse into the only condition when the scope adds no WHERE.
        if ($user && ! $user->hasGlobalVisibility()) {
            $baseQuery->orWhereHas('collaborators', fn (Builder $q) => $q->where('users.id', $user->id));
        }

        $statuses = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost'];
        $counts = (clone $baseQuery)
            ->whereIn('status', $statuses)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $labels = ['New', 'Contacted', 'Qualified', 'Proposal', 'Negotiation', 'Converted', 'Not Converted'];
        $data = array_map(fn ($s) => $counts[$s] ?? 0, $statuses);

        return [
            'datasets' => [
                [
                    'label' => 'Leads',
                    'data' => $data,
                    'backgroundColor' => [
                        'rgb(107, 114, 128)',  // gray — new
                        'rgb(14, 165, 233)',   // info — contacted
                        'rgb(168, 85, 247)',   // purple — qualified
                        'rgb(59, 130, 246)',   // blue — proposal
                        'rgb(234, 179, 8)',    // warning — negotiation
                        'rgb(34, 197, 94)',    // success — converted
                        'rgb(239, 68, 68)',    // danger — not converted
                    ],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * A status breakdown is a part-to-whole read, so a doughnut beats bars:
     * the question is each stage's share of the pipeline, not its absolute
     * height.
     */
    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'cutout' => '55%',
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'right',
                ],
            ],
        ];
    }
}
