<?php

namespace App\Services\Reports;

use App\DTOs\CustomerReportData;
use App\DTOs\ReportFilterData;
use App\Models\Order;
use App\Services\PrincipalScopeService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CustomerReportService
{
    public function generate(ReportFilterData $filters): CustomerReportData
    {
        return Cache::remember(
            "customer_report_{$filters->toCacheKey()}",
            now()->addMinutes(5),
            fn () => $this->calculateReport($filters)
        );
    }

    private function calculateReport(ReportFilterData $filters): CustomerReportData
    {
        $primaryQuery = $this->buildBaseQuery($filters);

        $totalRevenue = (clone $primaryQuery)->sum('total_amount');
        $customerIds = (clone $primaryQuery)->distinct()->pluck('end_customer_id')->filter();
        $totalCustomers = $customerIds->count();

        $newCustomerIds = $this->getNewCustomerIds($filters, $customerIds);
        $newCustomers = $newCustomerIds->count();
        $returningCustomers = $totalCustomers - $newCustomers;

        $averageRevenuePerCustomer = $totalCustomers > 0 ? $totalRevenue / $totalCustomers : 0;

        return new CustomerReportData(
            topCustomers: $this->getTopCustomers($filters),
            revenueByCustomerGroup: $this->getRevenueByCustomerGroup($filters),
            revenueBySegment: $this->getRevenueBySegment($filters),
            totalCustomers: $totalCustomers,
            newCustomers: $newCustomers,
            returningCustomers: $returningCustomers,
            totalRevenue: $totalRevenue,
            averageRevenuePerCustomer: $averageRevenuePerCustomer,
            monthlyTrend: $this->getMonthlyTrend($filters),
        );
    }

    private function buildBaseQuery(ReportFilterData $filters): Builder
    {
        $query = Order::query()
            ->whereBetween('order_date', [$filters->startDate, $filters->endDate]);

        $query = PrincipalScopeService::applyToOrders($query, $filters->principalIds);

        if ($filters->userId) {
            $query->where('created_by', $filters->userId);
        }

        if (! empty($filters->userIds)) {
            $query->whereIn('created_by', $filters->userIds);
        }

        // Territory filter: join through users table (leftJoin — not every user has a territory)
        if ($filters->territoryId) {
            $query->leftJoin('users', 'orders.created_by', '=', 'users.id')
                ->where('users.territory_id', $filters->territoryId);
        }

        if ($filters->customerId) {
            $query->where('end_customer_id', $filters->customerId);
        }

        // Customer filters: join customers ONCE if any are active
        if ($filters->customerGroupId || $filters->segmentId || $filters->cdNcdType) {
            $query->join('customers', 'orders.end_customer_id', '=', 'customers.id');
        }

        if ($filters->customerGroupId) {
            $query->where('customers.customer_group_id', $filters->customerGroupId);
        }

        if ($filters->segmentId) {
            $query->where('customers.segment_id', $filters->segmentId);
        }

        if ($filters->cdNcdType) {
            $query->where('customers.cd_ncd_type', $filters->cdNcdType);
        }

        return $query;
    }

    private function getNewCustomerIds(ReportFilterData $filters, Collection $periodCustomerIds): Collection
    {
        $beforePeriodCustomers = Order::query()
            ->where('order_date', '<', $filters->startDate)
            ->distinct()
            ->pluck('end_customer_id');

        return $periodCustomerIds->diff($beforePeriodCustomers);
    }

    private function buildBreakdownQuery(ReportFilterData $filters): Builder
    {
        $query = $this->buildBaseQuery($filters);

        $hasCustomersJoin = collect($query->getQuery()->joins ?? [])
            ->contains(fn ($join) => ($join instanceof JoinClause ? $join->table : $join) === 'customers');

        return $hasCustomersJoin
            ? $query
            : $query->join('customers', 'orders.end_customer_id', '=', 'customers.id');
    }

    private function getTopCustomers(ReportFilterData $filters): Collection
    {
        return $this->buildBaseQuery($filters)
            ->selectRaw('end_customer_id, SUM(total_amount) as revenue, COUNT(*) as orders')
            ->with('customer:id,name,cd_ncd_type')
            ->whereNotNull('end_customer_id')
            ->groupBy('end_customer_id')
            ->orderByDesc('revenue')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'customer_id' => $row->end_customer_id,
                'name' => $row->customer?->name ?? 'Unknown',
                'cd_ncd_type' => $row->customer?->cd_ncd_type,
                'revenue' => (float) $row->revenue,
                'orders' => $row->orders,
            ]);
    }

    private function getRevenueByCustomerGroup(ReportFilterData $filters): Collection
    {
        return $this->buildBreakdownQuery($filters)
            ->leftJoin('customer_groups', 'customers.customer_group_id', '=', 'customer_groups.id')
            ->selectRaw('customers.customer_group_id, customer_groups.name as group_name, SUM(orders.total_amount) as revenue, COUNT(*) as orders')
            ->whereNotNull('customers.customer_group_id')
            ->groupBy('customers.customer_group_id', 'customer_groups.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'customer_group_id' => $row->customer_group_id,
                'name' => $row->group_name ?? 'Unknown',
                'revenue' => (float) $row->revenue,
                'orders' => $row->orders,
            ]);
    }

    private function getRevenueBySegment(ReportFilterData $filters): Collection
    {
        return $this->buildBreakdownQuery($filters)
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
        return $this->buildBaseQuery($filters)
            ->selectRaw('YEAR(order_date) as year, MONTH(order_date) as month, SUM(total_amount) as revenue, COUNT(DISTINCT end_customer_id) as customers')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'period' => Carbon::create($row->year, $row->month)->format('M Y'),
                'revenue' => (float) $row->revenue,
                'customers' => $row->customers,
            ]);
    }

    public function getExportData(ReportFilterData $filters): Collection
    {
        return $this->buildBaseQuery($filters)
            ->with(['customer:id,name,segment_id,customer_group_id', 'customer.segment:id,name', 'customer.customerGroup:id,name'])
            ->orderBy('order_date', 'desc')
            ->get()
            ->map(fn ($order) => [
                'Customer' => $order->customer?->name,
                'Customer Group' => $order->customer?->customerGroup?->name,
                'Segment' => $order->customer?->segment?->name,
                'CD/NCD Type' => $order->customer?->cd_ncd_type,
                'Order Number' => $order->order_number,
                'Order Date' => $order->order_date?->format('d M Y'),
                'Revenue' => $order->total_amount,
                'Net Sales' => $order->net_sales_total,
            ]);
    }
}
