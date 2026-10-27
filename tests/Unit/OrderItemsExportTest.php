<?php

use App\Exports\OrderItemsExport;

it('exports current order item values and skips rows without a product', function () {
    $export = new OrderItemsExport([
        [
            'item_id' => 1,
            'item_name' => 'Analyzer',
            'principal_name' => 'Acme',
            'quantity' => 2,
            'price_type' => 'ecatalog_price',
            'unit_price' => 1250,
            'subtotal' => 2500,
        ],
        ['item_id' => null, 'quantity' => 3],
    ]);

    expect($export->headings())->toBe([
        'Principal', 'Product', 'Quantity', 'Price Type', 'Unit Price (IDR)', 'Line Total (IDR)',
    ])->and($export->collection()->all())->toBe([
        [
            'principal' => 'Acme',
            'product' => 'Analyzer',
            'quantity' => 2,
            'price_type' => 'E-Catalog Price',
            'unit_price' => 1250,
            'line_total' => 2500,
        ],
    ]);
});
