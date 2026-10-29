<?php

namespace App\Services;

use App\Filament\Resources\Leads\Tables\LeadsTable;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Principal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for the principal detail page: which orders and leads
 * belong to a principal, and the totals shown in its stat widgets.
 */
class PrincipalMetricsService
{
    /** Orders that contain products of the principal. */
    public static function ordersQuery(Principal $principal): Builder
    {
        return Order::query()->forPrincipal($principal->getKey());
    }

    /**
     * Leads that list products of the principal, narrowed to the leads the
     * user may see so the totals match the leads table below them.
     */
    public static function leadsQuery(Principal $principal, ?User $user = null): Builder
    {
        $query = Lead::query()->forPrincipal($principal->getKey());

        LeadsTable::listVisibilityQuery($query, $user);

        return $query;
    }

    /**
     * @return array{orders: int, leads: int, actual_sales: float, expected_revenue: float}
     */
    public static function totals(Principal $principal, ?User $user = null): array
    {
        $orders = self::ordersQuery($principal);

        return [
            'orders' => (clone $orders)->count(),
            'leads' => self::leadsQuery($principal, $user)->count(),
            'actual_sales' => self::actualSales($principal),
            'expected_revenue' => self::expectedRevenue($principal, $user),
        ];
    }

    private static function expectedRevenue(Principal $principal, ?User $user): float
    {
        $principalProductIds = $principal->products()->select('id');

        return (float) self::leadsQuery($principal, $user)
            ->whereHas('leadProductItems', function (Builder $items) use ($principal, $principalProductIds): void {
                $items->where('principal_id', $principal->getKey())
                    ->orWhereIn('product_id', $principalProductIds);
            })
            ->sum('estimated_revenue');
    }

    private static function actualSales(Principal $principal): float
    {
        $principalProductIds = $principal->products()->select('id');
        $lineSales = (float) DB::table('order_items')
            ->where(function ($items) use ($principal, $principalProductIds): void {
                $items->where('principal_id', $principal->getKey())
                    ->orWhereIn('item_id', $principalProductIds);
            })
            ->sum('subtotal');

        // Imported orders have a header principal and stored net sales but no lines.
        $importedOrderSales = (float) Order::query()
            ->where('principal_id', $principal->getKey())
            ->whereDoesntHave('orderItems')
            ->sum('net_sales_total');

        return $lineSales + $importedOrderSales;
    }
}
