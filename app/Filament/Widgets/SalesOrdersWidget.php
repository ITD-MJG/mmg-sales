<?php

namespace App\Filament\Widgets;

use App\Models\Order;
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

        $openOrders = Order::query()
            ->whereDoesntHave('deliveryStatuses')
            ->whereDoesntHave('paymentStatuses')
            ->whereYear('order_date', now()->year);

        $openCount = $openOrders->count();

        $shippedOrders = Order::query()
            ->where('created_by', $user->id)
            ->whereHas('deliveryStatuses', fn ($q) => $q->whereNotNull('shipped_date'))
            ->whereYear('order_date', now()->year);

        $shippedCount = $shippedOrders->count();

        $paidOrders = Order::query()
            ->where('created_by', $user->id)
            ->whereHas('paymentStatuses', fn ($q) => $q->where('status', 'full'))
            ->whereYear('order_date', now()->year);

        $paidCount = $paidOrders->count();

        $totalOrders = Order::query()->count();

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
