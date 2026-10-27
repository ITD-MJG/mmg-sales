<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class OrderItemsExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private readonly array $items) {}

    public function collection(): Collection
    {
        return collect($this->items)
            ->filter(fn (array $item): bool => filled($item['item_id'] ?? null))
            ->map(fn (array $item): array => [
                'principal' => $item['principal_name'] ?? '',
                'product' => $item['item_name'] ?? '',
                'quantity' => $item['quantity'] ?? '',
                'price_type' => ($item['price_type'] ?? '') === 'ecatalog_price' ? 'E-Catalog Price' : 'Unit Price',
                'unit_price' => $item['unit_price'] ?? '',
                'line_total' => $item['subtotal'] ?? '',
            ])
            ->values();
    }

    public function headings(): array
    {
        return ['Principal', 'Product', 'Quantity', 'Price Type', 'Unit Price (IDR)', 'Line Total (IDR)'];
    }

    public function title(): string
    {
        return 'Order Products';
    }
}
