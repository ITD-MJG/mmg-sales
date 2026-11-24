@php
    /**
     * The product lines on a lead, with the value of the whole list underneath.
     *
     * Styled inline rather than with Tailwind utilities: this partial is served
     * inside a modal and must not depend on an app CSS rebuild picking up
     * classes that only exist once the host has compiled it.
     *
     * @var \Illuminate\Support\Collection<int, \App\Models\LeadProduct> $items
     * @var float $total
     */
    $formatter = new NumberFormatter('id_ID', NumberFormatter::CURRENCY);
    $money = fn (float $amount): string => $formatter->formatCurrency($amount, 'IDR');
    $value = fn ($amount): float => (float) ($amount ?? 0);
@endphp

<div>
    @if ($items->isEmpty())
        <p style="padding: .75rem 0; font-size: .875rem; opacity: .7">
            No products linked to this lead.
        </p>
    @else
        <table style="width: 100%; border-collapse: collapse; font-size: .875rem">
            <thead>
                <tr>
                    <th style="padding: .5rem .75rem; text-align: left; font-weight: 600; white-space: nowrap; border-bottom: 1px solid rgba(128, 128, 128, .35)">Product</th>
                    <th style="padding: .5rem .75rem; text-align: left; font-weight: 600; white-space: nowrap; border-bottom: 1px solid rgba(128, 128, 128, .35)">Principal</th>
                    <th style="padding: .5rem .75rem; text-align: right; font-weight: 600; white-space: nowrap; border-bottom: 1px solid rgba(128, 128, 128, .35)">Qty</th>
                    <th style="padding: .5rem .75rem; text-align: right; font-weight: 600; white-space: nowrap; border-bottom: 1px solid rgba(128, 128, 128, .35)">Unit Price</th>
                    <th style="padding: .5rem .75rem; text-align: right; font-weight: 600; white-space: nowrap; border-bottom: 1px solid rgba(128, 128, 128, .35)">Line Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td style="padding: .5rem .75rem; border-bottom: 1px solid rgba(128, 128, 128, .15)">
                            {{ $item->product?->name ?? 'Unknown product' }}
                        </td>
                        <td style="padding: .5rem .75rem; border-bottom: 1px solid rgba(128, 128, 128, .15)">
                            {{ $item->principal?->name ?? $item->product?->principal?->name ?? '-' }}
                        </td>
                        <td style="padding: .5rem .75rem; text-align: right; white-space: nowrap; border-bottom: 1px solid rgba(128, 128, 128, .15)">
                            {{ number_format((int) $item->quantity) }}
                        </td>
                        <td style="padding: .5rem .75rem; text-align: right; white-space: nowrap; border-bottom: 1px solid rgba(128, 128, 128, .15)">
                            {{ $item->unit_price === null ? '-' : $money($value($item->unit_price)) }}
                        </td>
                        <td style="padding: .5rem .75rem; text-align: right; white-space: nowrap; border-bottom: 1px solid rgba(128, 128, 128, .15)">
                            {{ $money((int) $item->quantity * $value($item->unit_price)) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" style="padding: .625rem .75rem; text-align: right; font-weight: 600; white-space: nowrap; border-top: 2px solid rgba(128, 128, 128, .35)">
                        Total
                    </td>
                    <td style="padding: .625rem .75rem; text-align: right; font-weight: 600; white-space: nowrap; border-top: 2px solid rgba(128, 128, 128, .35)">
                        {{ $money($total) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    @endif
</div>
