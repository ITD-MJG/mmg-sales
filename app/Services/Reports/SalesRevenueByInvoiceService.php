<?php

namespace App\Services\Reports;

use App\Filament\Resources\Orders\OrderResource;
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
     * @return array{rows: list<array<string, mixed>>, count: int, total: float}
     */
    public function invoices(string $dimension, string $metric, ?string $groupKey): array
    {
        $rows = [];

        foreach ($this->orders() as $order) {
            $weightsByDimension = $this->weights($order);
            $weights = $weightsByDimension[$dimension] ?? [];
            $weight = $groupKey === null ? 1.0 : ($weights[$groupKey]['weight'] ?? null);

            if ($weight === null) {
                continue;
            }

            $total = (float) $order->total_amount;
            $paid = $order->paidAmount();
            $amount = $metric === 'paid' ? $paid : $total;
            $paymentDate = $order->paymentStatuses
                ->whereIn('status', ['partial', 'full'])
                ->pluck('effective_date')
                ->filter()
                ->sortDesc()
                ->first();

            $rows[] = [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'url' => OrderResource::getUrl('view', ['record' => $order]),
                'order_date' => $order->order_date?->format('Y-m-d'),
                'payment_date' => $paymentDate?->format('Y-m-d'),
                'customer' => $order->customer?->name ?? 'Unassigned',
                'rep' => $order->sales?->name ?? 'Unassigned',
                'supervisor' => $order->sales?->manager?->name ?? 'Unassigned',
                'territory' => $order->sales?->territory?->name ?? 'Unassigned',
                'principals' => implode(', ', array_column($weightsByDimension['principal'], 'label')),
                'marketing' => implode(', ', array_column($weightsByDimension['marketing'], 'label')),
                'total' => $total,
                'paid' => $paid,
                'remaining' => max(0.0, $total - $paid),
                'share' => round($amount * $weight, 2),
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['share'] <=> $a['share']);

        return [
            'rows' => array_slice($rows, 0, 25),
            'count' => count($rows),
            'total' => round(array_sum(array_column($rows, 'share')), 2),
        ];
    }

    /**
     * @return array<string, array<string, array{label: string, weight: float}>>
     */
    private function weights(Order $order): array
    {
        $principals = $this->principalWeights($order);

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
            'principal' => $principals,
            'marketing' => $this->marketingWeights($order, $principals),
        ];
    }

    /**
     * @return array<string, array{label: string, weight: float}>
     */
    private function principalWeights(Order $order): array
    {
        $shares = $order->orderItems
            ->groupBy(fn ($item): string => $this->key($item->principal_id))
            ->map(fn (Collection $items): float => (float) $items->sum('subtotal'));

        if ($shares->sum() <= 0) {
            return [
                $this->key($order->principal_id) => [
                    'label' => $order->principal?->name ?? 'Unassigned',
                    'weight' => 1.0,
                ],
            ];
        }

        $total = (float) $shares->sum();

        return $shares
            ->mapWithKeys(fn (float $subtotal, string $key): array => [
                $key => [
                    'label' => $key === 'unassigned'
                        ? 'Unassigned'
                        : ($order->orderItems->firstWhere('principal_id', (int) $key)?->principal?->name ?? 'Unassigned'),
                    'weight' => $subtotal / $total,
                ],
            ])
            ->all();
    }

    /**
     * @param  array<string, array{label: string, weight: float}>  $principals
     * @return array<string, array{label: string, weight: float}>
     */
    private function marketingWeights(Order $order, array $principals): array
    {
        $result = [];

        foreach ($principals as $principalKey => $share) {
            $principal = $principalKey === 'unassigned'
                ? null
                : $order->orderItems->firstWhere('principal_id', (int) $principalKey)?->principal;
            $users = $principal?->marketingUsers ?? collect();

            if ($users->isEmpty()) {
                $result['unassigned'] ??= ['label' => 'Unassigned Marketing', 'weight' => 0.0];
                $result['unassigned']['weight'] += $share['weight'];

                continue;
            }

            foreach ($users as $user) {
                $result[(string) $user->id] ??= ['label' => $user->name, 'weight' => 0.0];
                $result[(string) $user->id]['weight'] += $share['weight'] / $users->count();
            }
        }

        return $result;
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
