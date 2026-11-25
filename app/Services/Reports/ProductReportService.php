<?php

namespace App\Services\Reports;

use App\DTOs\ProductReportData;
use App\DTOs\ReportFilterData;
use App\Models\Order;
use App\Services\PrincipalScopeService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ProductReportService
{
    public function generate(ReportFilterData $filters): ProductReportData
    {
        return Cache::remember(
            "product_report_{$filters->toCacheKey()}",
            now()->addMinutes(5),
            fn () => $this->calculateReport($filters)
        );
    }

    private function calculateReport(ReportFilterData $filters): ProductReportData
    {
        $primaryQuery = $this->buildBaseQuery($filters);

        $totalRevenue = (clone $primaryQuery)->sum('total_amount');
        $totalDiscount = (clone $primaryQuery)->sum('discount_amount');

        $totalQuantity = Order::query()
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.order_date', [$filters->startDate, $filters->endDate])
            ->sum('order_items.quantity');

        return new ProductReportData(
            topItems: $this->getTopItems($filters),
            revenueByPrincipal: $this->getRevenueByPrincipal($filters),
            quantityByItem: $this->getQuantityByItem($filters),
            revenueBySegment: $this->getRevenueBySegment($filters),
            totalQuantity: $totalQuantity,
            totalRevenue: $totalRevenue,
            totalDiscount: $totalDiscount,
            monthlyTrend: $this->getMonthlyTrend($filters),
        );
    }

    /**
     * Base query with date range and simple WHERE filters only (no joins).
     */
    private function buildBaseQuery(ReportFilterData $filters): Builder
    {
        $query = Order::query()
            ->whereBetween('order_date', [$filters->startDate, $filters->endDate]);

        $query = PrincipalScopeService::applyToOrders($query, $filters->principalIds);

        $this->applyFilters($query, $filters);

        return $query;
    }

    private function applyFilters(Builder $query, ReportFilterData $filters): void
    {
        if ($filters->userId) {
            $query->where('created_by', $filters->userId);
        }

        if (! empty($filters->userIds)) {
            $query->whereIn('created_by', $filters->userIds);
        }

        if ($filters->principalId) {
            $query->where('principal_id', $filters->principalId);
        }

        if ($filters->customerId) {
            $query->where('end_customer_id', $filters->customerId);
        }
    }

    private function getTopItems(ReportFilterData $filters): Collection
    {
        $query = $this->buildBaseQuery($filters);

        if ($filters->territoryId) {
            $query->leftJoin('users', 'orders.created_by', '=', 'users.id')
                ->where('users.territory_id', $filters->territoryId);
        }

        if ($filters->segmentId) {
            $query->leftJoin('customers', 'orders.end_customer_id', '=', 'customers.id')
                ->where('customers.segment_id', $filters->segmentId);
        }

        $query->leftJoin('order_items', 'orders.id', '=', 'order_items.order_id');

        if ($filters->itemId) {
            $query->where('order_items.item_id', $filters->itemId);
        }

        return $query
            ->leftJoin('products', 'order_items.item_id', '=', 'products.id')
            ->selectRaw('order_items.item_id, products.name as item_name, SUM(order_items.subtotal) as revenue, SUM(order_items.quantity) as quantity, COUNT(DISTINCT orders.id) as orders')
            ->whereNotNull('order_items.item_id')
            ->groupBy('order_items.item_id', 'products.name')
            ->orderByDesc('revenue')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'item_id' => $row->item_id,
                'name' => $row->item_name ?? 'Unknown',
                'revenue' => (float) $row->revenue,
                'quantity' => (int) $row->quantity,
                'orders' => $row->orders,
            ]);
    }

    private function getRevenueByPrincipal(ReportFilterData $filters): Collection
    {
        $query = $this->buildBaseQuery($filters);

        if ($filters->territoryId) {
            $query->leftJoin('users', 'orders.created_by', '=', 'users.id')
                ->where('users.territory_id', $filters->territoryId);
        }

        if ($filters->segmentId) {
            $query->leftJoin('customers', 'orders.end_customer_id', '=', 'customers.id')
                ->where('customers.segment_id', $filters->segmentId);
        }

        $query->leftJoin('order_items', 'orders.id', '=', 'order_items.order_id');

        if ($filters->itemId) {
            $query->where('order_items.item_id', $filters->itemId);
        }

        return $query
            ->leftJoin('principals', 'orders.principal_id', '=', 'principals.id')
            ->selectRaw('orders.principal_id, principals.name as principal_name, SUM(order_items.subtotal) as revenue, SUM(order_items.quantity) as quantity, COUNT(DISTINCT orders.id) as orders')
            ->whereNotNull('orders.principal_id')
            ->groupBy('orders.principal_id', 'principals.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'principal_id' => $row->principal_id,
                'name' => $row->principal_name ?? 'Unknown',
                'revenue' => (float) $row->revenue,
                'quantity' => (int) $row->quantity,
                'orders' => $row->orders,
            ]);
    }

    private function getQuantityByItem(ReportFilterData $filters): Collection
    {
        $query = $this->buildBaseQuery($filters);

        if ($filters->territoryId) {
            $query->leftJoin('users', 'orders.created_by', '=', 'users.id')
                ->where('users.territory_id', $filters->territoryId);
        }

        if ($filters->segmentId) {
            $query->leftJoin('customers', 'orders.end_customer_id', '=', 'customers.id')
                ->where('customers.segment_id', $filters->segmentId);
        }

        $query->leftJoin('order_items', 'orders.id', '=', 'order_items.order_id');

        if ($filters->itemId) {
            $query->where('order_items.item_id', $filters->itemId);
        }

        return $query
            ->leftJoin('products', 'order_items.item_id', '=', 'products.id')
            ->selectRaw('order_items.item_id, products.name as item_name, SUM(order_items.quantity) as quantity')
            ->whereNotNull('order_items.item_id')
            ->groupBy('order_items.item_id', 'products.name')
            ->orderByDesc('quantity')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'item_id' => $row->item_id,
                'name' => $row->item_name ?? 'Unknown',
                'quantity' => (int) $row->quantity,
            ]);
    }

    private function getRevenueBySegment(ReportFilterData $filters): Collection
    {
        $query = $this->buildBaseQuery($filters);

        if ($filters->itemId) {
            $query->leftJoin('order_items', 'orders.id', '=', 'order_items.order_id')
                ->where('order_items.item_id', $filters->itemId);
        }

        if ($filters->territoryId) {
            $query->leftJoin('users', 'orders.created_by', '=', 'users.id')
                ->where('users.territory_id', $filters->territoryId);
        }

        $query->join('customers', 'orders.end_customer_id', '=', 'customers.id');

        if ($filters->segmentId) {
            $query->where('customers.segment_id', $filters->segmentId);
        }

        return $query
            ->leftJoin('segments', 'customers.segment_id', '=', 'segments.id')
            ->selectRaw('customers.segment_id, segments.name as segment_name, SUM(orders.total_amount) as revenue, COUNT(*) as orders')
            ->whereNotNull('customers.segment_id')
            ->groupBy('customers.segment_id', 'segments.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'segment_id' => $row->segment_id,
                'name' => $row->segment_name ?? 'Unknown',
                'revenue' => (float) $row->revenue,
                'orders' => $row->orders,
            ]);
    }

    private function getMonthlyTrend(ReportFilterData $filters): Collection
    {
        $query = $this->buildBaseQuery($filters);

        if ($filters->territoryId) {
            $query->leftJoin('users', 'orders.created_by', '=', 'users.id')
                ->where('users.territory_id', $filters->territoryId);
        }

        if ($filters->segmentId) {
            $query->leftJoin('customers', 'orders.end_customer_id', '=', 'customers.id')
                ->where('customers.segment_id', $filters->segmentId);
        }

        $query->leftJoin('order_items', 'orders.id', '=', 'order_items.order_id');

        if ($filters->itemId) {
            $query->where('order_items.item_id', $filters->itemId);
        }

        return $query
            ->selectRaw('YEAR(orders.order_date) as year, MONTH(orders.order_date) as month, SUM(order_items.subtotal) as revenue, SUM(order_items.quantity) as quantity')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'period' => Carbon::create($row->year, $row->month)->format('M Y'),
                'revenue' => (float) $row->revenue,
                'quantity' => (int) $row->quantity,
            ]);
    }

    public function getExportData(ReportFilterData $filters): Collection
    {
        $query = $this->buildBaseQuery($filters);

        if ($filters->itemId) {
            $query->leftJoin('order_items', 'orders.id', '=', 'order_items.order_id')
                ->where('order_items.item_id', $filters->itemId);
        }

        if ($filters->territoryId) {
            $query->leftJoin('users', 'orders.created_by', '=', 'users.id')
                ->where('users.territory_id', $filters->territoryId);
        }

        if ($filters->segmentId) {
            $query->leftJoin('customers', 'orders.end_customer_id', '=', 'customers.id')
                ->where('customers.segment_id', $filters->segmentId);
        }

        return $query
            ->with([
                'principal:id,name',
                'orderItems.item:id,name',
                'customer.segment:id,name',
            ])
            ->orderBy('order_date', 'desc')
            ->get()
            ->map(fn ($order) => [
                'Item' => $order->orderItems->first()?->item?->name,
                'Principal' => $order->principal?->name,
                'Segment' => $order->customer?->segment?->name,
                'Order Number' => $order->order_number,
                'Order Date' => $order->order_date?->format('d M Y'),
                'Quantity' => $order->orderItems->sum('quantity'),
                'Unit Price' => $order->orderItems->first()?->unit_price ?? 0,
                'Gross Sales' => $order->orderItems->sum('subtotal'),
                'Discount' => $order->discount_amount,
                'Net Sales' => $order->net_sales_total,
                'Total Amount' => $order->total_amount,
            ]);
    }
}
