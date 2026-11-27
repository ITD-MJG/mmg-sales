<?php

namespace App\Services\Reports;

use App\Filament\Traits\HasVisibilityScope;
use App\Models\Order;
use App\Models\User;
use App\Services\PrincipalScopeService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class SalesRevenueByInvoiceService
{
    use HasVisibilityScope;

    public const DIMENSIONS = [
        'territory', 'sales_rep', 'sales_supervisor', 'principal', 'marketing', 'customer',
    ];

    public const METRICS = ['total', 'paid'];

    public function __construct(
        private CarbonInterface $startDate,
        private CarbonInterface $endDate,
        private ?User $user = null,
    ) {}

    /**
     * @return list<array{key: string, label: string, value: float}>
     */
    public function breakdown(string $dimension, string $metric): array
    {
        $totals = [];

        foreach ($this->orders() as $order) {
            $amount = $metric === 'paid' ? $order->paidAmount() : (float) $order->total_amount;

            foreach ($this->weights($order)[$dimension] ?? [] as $key => $group) {
                $totals[$key] ??= ['key' => $key, 'label' => $group['label'], 'value' => 0.0];
                $totals[$key]['value'] += $amount * $group['weight'];
            }
        }

        usort($totals, fn (array $a, array $b): int => $b['value'] <=> $a['value']);

        return array_map(
            fn (array $row): array => [...$row, 'value' => round($row['value'], 2)],
            $totals,
        );
    }

    /**
     * @return array<string, array<string, array{label: string, weight: float}>>
     */
    private function weights(Order $order): array
    {
        return [
            'territory' => [
                $this->key($order->sales?->territory_id) => [
                    'label' => $order->sales?->territory?->name ?? 'Unassigned',
                    'weight' => 1.0,
                ],
            ],
            'sales_rep' => [
                $this->key($order->sales_id) => [
                    'label' => $order->sales?->name ?? 'Unassigned',
                    'weight' => 1.0,
                ],
            ],
            'sales_supervisor' => [
                $this->key($order->sales?->manager_id) => [
                    'label' => $order->sales?->manager?->name ?? 'Unassigned',
                    'weight' => 1.0,
                ],
            ],
            'customer' => [
                $this->key($order->end_customer_id) => [
                    'label' => $order->customer?->name ?? 'Unassigned',
                    'weight' => 1.0,
                ],
            ],
            'principal' => [],
            'marketing' => [],
        ];
    }

    private function key(int|string|null $value): string
    {
        return $value === null ? 'unassigned' : (string) $value;
    }

    /**
     * @return Collection<int, Order>
     */
    private function orders(): Collection
    {
        $query = Order::query()
            ->whereBetween('orders.order_date', [
                $this->startDate->toDateString(),
                $this->endDate->toDateString(),
            ])
            ->whereHas('paymentStatuses', fn (Builder $payments): Builder => $payments->whereIn('status', ['partial', 'full']));

        $query = PrincipalScopeService::orders($query, $this->user);

        $visibleIds = self::visibleUserIds($this->user);

        if ($visibleIds !== null) {
            $query->whereIn('orders.sales_id', $visibleIds);
        }

        return $query
            ->with([
                'sales.territory',
                'sales.manager',
                'customer',
                'paymentStatuses',
                'principal.marketingUsers',
                'orderItems.principal.marketingUsers',
            ])
            ->get();
    }
}
