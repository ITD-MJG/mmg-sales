<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Services\PrincipalScopeService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use NumberFormatter;

class SalesOrdersWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        $formatter = new NumberFormatter('id_ID', NumberFormatter::CURRENCY);

        // Staff outside Sales and Marketing are not credited per person, so
        // the personal filters would hide most of the company's orders.
        $wholeCompany = ! $user->isSalesOrMarketingDepartment();

        $orders = fn () => PrincipalScopeService::orders(Order::query(), $user);
        $onlyOwn = fn ($query) => $wholeCompany ? $query : $query->where('created_by', $user->id);

        $openOrders = $orders()
            ->whereDoesntHave('deliveryStatuses')
            ->whereDoesntHave('paymentStatuses')
            ->whereYear('order_date', now()->year);

        $openCount = $openOrders->count();

        $shippedOrders = $onlyOwn($orders())
            ->whereHas('deliveryStatuses', fn ($q) => $q->whereNotNull('shipped_date'))
            ->whereYear('order_date', now()->year);

        $shippedCount = $shippedOrders->count();

        $paidOrders = $onlyOwn($orders())
            ->whereHas('paymentStatuses', fn ($q) => $q->where('status', 'full'))
            ->whereYear('order_date', now()->year);

        $paidCount = $paidOrders->count();

        $totalOrders = $orders()->count();

        return [
            Stat::make('Open Orders', $openCount.' orders')
                ->description('YTD')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            Stat::make('Shipped Orders', $shippedCount.' orders')
                ->description('YTD')
                ->descriptionIcon('heroicon-m-truck')
                ->color('primary'),
            Stat::make('Paid Orders', $paidCount.' orders')
                ->description('YTD')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Total Orders', $totalOrders.' orders')
                ->description('All-time')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('info'),
        ];
    }
}
