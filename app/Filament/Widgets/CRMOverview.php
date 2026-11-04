<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Lead;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use NumberFormatter;

class CRMOverview extends BaseWidget
{
    protected ?string $heading = 'CRM Analytics';

    public static function canView(): bool
    {
        return true;
    }

    protected function getStats(): array
    {
        $totalCompanies = Customer::count();
        $totalLeads = Lead::count();

        // A lead is open until it is converted or disqualified; the 'won'/'lost'
        // stages belong to opportunities, so filtering on them never matched.
        $openLeads = Lead::query()->whereNotIn('status', ['converted', 'disqualified']);

        $pipelineValue = (clone $openLeads)->sum('estimated_revenue');

        // Weighted forecast: revenue discounted by the lead's confidence band.
        // Leads nobody has assessed contribute nothing and are counted instead
        // of being silently treated as a coin flip.
        $weightedValue = (clone $openLeads)
            ->whereNotNull('confidence_level')
            ->whereNotNull('estimated_revenue')
            ->get(['estimated_revenue', 'confidence_level'])
            ->sum(fn (Lead $lead): float => $lead->weighted_revenue ?? 0.0);

        $unassessedLeads = (clone $openLeads)->whereNull('confidence_level')->count();

        // Stale Leads: No contact for > 30 days
        $staleLeadsCount = Lead::whereNotIn('status', ['converted', 'disqualified'])
            ->where(function ($query) {
                $query->where('last_contacted_at', '<', Carbon::now()->subDays(30))
                    ->orWhereNull('last_contacted_at');
            })
            ->count();

        $formatter = new NumberFormatter('id_ID', NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 0);

        return [
            Stat::make('Total Companies', $totalCompanies)
                ->description('Active facilities')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary')
                ->url(route('filament.admin.resources.companies.index')),

            Stat::make('Pipeline Value', $formatter->formatCurrency($pipelineValue, 'IDR'))
                ->description('Potential revenue from open leads')
                ->descriptionIcon('heroicon-m-presentation-chart-line')
                ->color('success')
                ->url(route('filament.admin.resources.leads.index')),

            Stat::make('Weighted Forecast', $formatter->formatCurrency($weightedValue, 'IDR'))
                ->description($unassessedLeads > 0
                    ? "{$unassessedLeads} open leads not assessed"
                    : 'All open leads assessed')
                ->descriptionIcon('heroicon-m-scale')
                ->color($unassessedLeads > 0 ? 'warning' : 'success')
                ->url(route('filament.admin.resources.leads.index')),

            Stat::make('Stale Leads', $staleLeadsCount)
                ->description('No contact for > 30 days')
                ->descriptionIcon('heroicon-m-clock')
                ->color($staleLeadsCount > 0 ? 'warning' : 'gray')
                ->url(route('filament.admin.resources.leads.index')),
        ];
    }
}
