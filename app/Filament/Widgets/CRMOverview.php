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

        // Open means anything short of a closed stage.
        $openLeads = Lead::query()->whereNotIn('status', Lead::CLOSED_STATUSES);

        $pipelineValue = (clone $openLeads)->sum('estimated_revenue');

        // Stale Leads: No contact for > 30 days
        $staleLeadsCount = Lead::whereNotIn('status', Lead::CLOSED_STATUSES)
            ->where(function ($query) {
                $query->where('last_contacted_at', '<', Carbon::now()->subDays(30))
                    ->orWhereNull('last_contacted_at');
            })
            ->count();

        $formatter = new NumberFormatter('id_ID', NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 0);

        return [
            // No `companies` resource exists — customers are the company
            // record here, so this stat links to the customer list.
            Stat::make('Total Companies', $totalCompanies)
                ->description('Active facilities')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary')
                ->url(route('filament.admin.resources.customers.index')),

            Stat::make('Pipeline Value', $formatter->formatCurrency($pipelineValue, 'IDR'))
                ->description('Potential revenue from open leads')
                ->descriptionIcon('heroicon-m-presentation-chart-line')
                ->color('success')
                ->url(route('filament.admin.resources.leads.index')),

            Stat::make('Stale Leads', $staleLeadsCount)
                ->description('No contact for > 30 days')
                ->descriptionIcon('heroicon-m-clock')
                ->color($staleLeadsCount > 0 ? 'warning' : 'gray')
                ->url(route('filament.admin.resources.leads.index')),
        ];
    }
}
