{{-- Payment history for UpdatePaymentAction's modal. Expects $order, $payments, $paid, $remaining. --}}

@php
    $money = fn (float $value): string => 'IDR '.number_format($value, 0, ',', '.');
    $total = (float) $order->total_amount;
@endphp

<div class="space-y-4">
    <dl class="grid grid-cols-3 gap-3">
        @foreach ([
            ['label' => 'Order Total', 'value' => $money($total), 'tone' => 'text-gray-950 dark:text-white'],
            ['label' => 'Paid', 'value' => $money((float) $paid), 'tone' => 'text-success-600 dark:text-success-400'],
            ['label' => 'Remaining', 'value' => $money((float) $remaining), 'tone' => 'text-warning-600 dark:text-warning-400'],
        ] as $stat)
            <div class="rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</dt>
                <dd class="mt-1 text-sm font-semibold tabular-nums {{ $stat['tone'] }}">{{ $stat['value'] }}</dd>
            </div>
        @endforeach
    </dl>

    @if ($payments->isEmpty())
        <p class="rounded-lg border border-dashed border-gray-300 px-3 py-6 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
            No payments recorded yet.
        </p>
    @else
        <div class="overflow-x-auto rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
            <table class="w-full text-start text-sm">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        @foreach (['Effective Date', 'Status', 'Amount', 'Notes', 'Recorded'] as $heading)
                            <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 dark:text-gray-400">
                                {{ $heading }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-950/5 dark:divide-white/10">
                    @foreach ($payments as $payment)
                        <tr wire:key="payment-{{ $payment->getKey() }}">
                            <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-white">
                                {{ $payment->effective_date?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">
                                <x-filament::badge
                                    :color="match ($payment->status) {
                                        'full' => 'success',
                                        'partial' => 'warning',
                                        default => 'gray',
                                    }"
                                >
                                    {{ ucfirst($payment->status) }}
                                </x-filament::badge>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 tabular-nums text-gray-950 dark:text-white">
                                {{ $money((float) $payment->amount) }}
                            </td>
                            <td class="px-3 py-2 text-gray-500 dark:text-gray-400">
                                {{ $payment->notes ?: '—' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-500 dark:text-gray-400">
                                {{ $payment->created_at?->format('d M Y H:i') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
