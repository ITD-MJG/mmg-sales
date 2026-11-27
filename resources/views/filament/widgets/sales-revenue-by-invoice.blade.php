@php
    $groups = $this->getBreakdown();
    $invoices = $this->getInvoices();
    $max = max(array_column($groups, 'value')) ?: 1;
    $format = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Sales Revenue (By Invoice)
        </x-slot>

        <x-slot name="description">
            {{ $this->selectedKey === null ? 'All invoices' : 'Selected: '.$this->getDashboardLabel() }}
        </x-slot>

        <div class="mb-4 flex flex-wrap items-end gap-4">
            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-200">By</span>
                <select wire:model.live="dimension" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    @foreach ($this->getDimensionOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-200">Value</span>
                <select wire:model.live="metric" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    @foreach ($this->getMetricOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        @forelse ($groups as $group)
            <button
                type="button"
                wire:key="group-{{ $dimension }}-{{ $group['key'] }}"
                wire:click="selectGroup('{{ $group['key'] }}')"
                @class([
                    'mb-2 block w-full rounded-lg px-3 py-2 text-left transition',
                    'bg-primary-50 ring-2 ring-primary-500 dark:bg-primary-500/10' => $selectedKey === $group['key'],
                    'hover:bg-gray-50 dark:hover:bg-gray-800' => $selectedKey !== $group['key'],
                ])
            >
                <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                    <span class="font-medium text-gray-900 dark:text-white">{{ $group['label'] }}</span>
                    <span class="tabular-nums text-gray-600 dark:text-gray-300">{{ $format($group['value']) }}</span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                    <div class="h-full rounded-full bg-primary-500" style="width: {{ min(100, ($group['value'] / $max) * 100) }}%"></div>
                </div>
            </button>
        @empty
            <p class="py-4 text-sm text-gray-500 dark:text-gray-400">No paid invoices in this period.</p>
        @endforelse

        <div class="mt-6 flex items-center justify-between gap-3 text-sm">
            <h3 class="font-semibold text-gray-900 dark:text-white">
                Invoices: {{ $this->getDashboardLabel() }}
            </h3>
            <span class="text-gray-500 dark:text-gray-400">
                Showing {{ count($invoices['rows']) }} of {{ $invoices['count'] }}
            </span>
        </div>

        <div class="mt-2 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="py-2 pr-3">Invoice</th>
                        <th class="py-2 pr-3">Order Date</th>
                        <th class="py-2 pr-3">Payment Date</th>
                        <th class="py-2 pr-3">Customer</th>
                        <th class="py-2 pr-3">Rep</th>
                        <th class="py-2 pr-3">Supervisor</th>
                        <th class="py-2 pr-3">Territory</th>
                        <th class="py-2 pr-3">Principals</th>
                        <th class="py-2 pr-3">Marketing</th>
                        <th class="py-2 pr-3 text-right">Total</th>
                        <th class="py-2 pr-3 text-right">Paid</th>
                        <th class="py-2 pr-3 text-right">Remaining</th>
                        <th class="py-2 text-right">Share</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($invoices['rows'] as $row)
                        <tr wire:key="invoice-{{ $row['order_id'] }}">
                            <td class="py-2 pr-3">
                                <a href="{{ $row['url'] }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                    {{ $row['order_number'] }}
                                </a>
                            </td>
                            <td class="py-2 pr-3">{{ $row['order_date'] }}</td>
                            <td class="py-2 pr-3">{{ $row['payment_date'] ?? '—' }}</td>
                            <td class="py-2 pr-3">{{ $row['customer'] }}</td>
                            <td class="py-2 pr-3">{{ $row['rep'] }}</td>
                            <td class="py-2 pr-3">{{ $row['supervisor'] }}</td>
                            <td class="py-2 pr-3">{{ $row['territory'] }}</td>
                            <td class="py-2 pr-3">{{ $row['principals'] }}</td>
                            <td class="py-2 pr-3">{{ $row['marketing'] }}</td>
                            <td class="py-2 pr-3 text-right tabular-nums">{{ $format($row['total']) }}</td>
                            <td class="py-2 pr-3 text-right tabular-nums">{{ $format($row['paid']) }}</td>
                            <td class="py-2 pr-3 text-right tabular-nums">{{ $format($row['remaining']) }}</td>
                            <td class="py-2 text-right tabular-nums font-medium">{{ $format($row['share']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
