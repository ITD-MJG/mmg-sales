{{-- Delivery history for UpdateDeliveryAction's modal. Expects $order, $deliveries. --}}

<div class="space-y-4">
    @if ($deliveries->isEmpty())
        <p class="rounded-lg border border-dashed border-gray-300 px-3 py-6 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
            No delivery updates recorded yet.
        </p>
    @else
        <div class="overflow-x-auto rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
            <table class="w-full text-start text-sm">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        @foreach (['Carrier', 'Tracking', 'Shipped', 'Delivered', 'Status', 'Notes', 'Recorded'] as $heading)
                            <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 dark:text-gray-400">
                                {{ $heading }}
                            </th>
                        @endforeach
                        <th class="px-3 py-2 text-end text-xs font-medium text-gray-500 dark:text-gray-400">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-950/5 dark:divide-white/10">
                    @foreach ($deliveries as $delivery)
                        <tr wire:key="delivery-{{ $delivery->getKey() }}">
                            <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-white">
                                {{ $delivery->carrier ?: '—' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-white">
                                {{ $delivery->tracking_number ?: '—' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-500 dark:text-gray-400">
                                {{ $delivery->shipped_date?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-500 dark:text-gray-400">
                                {{ $delivery->delivered_date?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">
                                <x-filament::badge
                                    :color="match ($delivery->status) {
                                        'delivered' => 'success',
                                        'shipped' => 'primary',
                                        'in_transit' => 'info',
                                        default => 'gray',
                                    }"
                                >
                                    {{ $delivery->status_label }}
                                </x-filament::badge>
                            </td>
                            <td class="px-3 py-2 text-gray-500 dark:text-gray-400">
                                {{ $delivery->notes ?: '—' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-500 dark:text-gray-400">
                                {{ $delivery->created_at?->format('d M Y H:i') }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-end">
                                <div class="flex items-center justify-end gap-2">
                                    <x-filament::button
                                        color="gray"
                                        size="xs"
                                        icon="heroicon-m-pencil-square"
                                        wire:click="mountAction('editDelivery', { delivery: {{ $delivery->getKey() }} })"
                                    >
                                        Edit
                                    </x-filament::button>

                                    <x-filament::button
                                        color="danger"
                                        size="xs"
                                        icon="heroicon-m-trash"
                                        outlined
                                        wire:click="mountAction('deleteDelivery', { delivery: {{ $delivery->getKey() }} })"
                                    >
                                        Delete
                                    </x-filament::button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
