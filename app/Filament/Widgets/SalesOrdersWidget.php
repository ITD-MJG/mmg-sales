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

        $openOrders = PrincipalScopeService::orders(Order::query(), $user)
            ->whereDoesntHave('deliveryStatuses')
            ->whereDoesntHave('paymentStatuses')
            ->whereYear('order_date', now()->year);

        $openCount = $openOrders->count();

        $shippedOrders = PrincipalScopeService::orders(Order::query(), $user)
            ->where('created_by', $user->id)
            ->whereHas('deliveryStatuses', fn ($q) => $q->whereNotNull('shipped_date'))
            ->whereYear('order_date', now()->year);

        $shippedCount = $shippedOrders->count();

        $paidOrders = PrincipalScopeService::orders(Order::query(), $user)
            ->where('created_by', $user->id)
            ->whereHas('paymentStatuses', fn ($q) => $q->where('status', 'full'))
            ->whereYear('order_date', now()->year);

        $paidCount = $paidOrders->count();

        $totalOrders = PrincipalScopeService::orders(Order::query(), $user)->count();

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
