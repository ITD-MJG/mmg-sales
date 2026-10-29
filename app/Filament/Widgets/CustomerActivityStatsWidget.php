<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\User;
use App\Services\ActivityScopeService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use NumberFormatter;

class CustomerActivityStatsWidget extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected function getStats(): array
    {
        if (! $this->record instanceof Customer) {
            return [];
        }

        /** @var User $user */
        $user = Auth::user();
        $service = app(ActivityScopeService::class);
        $stats = $service->getCustomerActivityStats($user, $this->record->id);

        $formatter = new NumberFormatter('id_ID', NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 0);

        return [
            Stat::make('Total Customer Interactions', $stats['total'])
                ->description('All time activities with this customer')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('primary'),
            Stat::make('Last Interaction', $stats['last_activity_date']?->format('d M Y') ?? 'No activities yet')
                ->description('Most recent interaction')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),
            Stat::make('Estimated Revenue', $formatter->formatCurrency($stats['estimated_revenue'], 'IDR'))
                ->description('Expected revenue from all leads of this customer')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
        ];
    }
}
