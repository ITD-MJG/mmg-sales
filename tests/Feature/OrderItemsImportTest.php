<?php

use App\Imports\OrderItemsImport;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Principal;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function () {
    HeadingRowFormatter::default('slug');
});

test('imports using either internal code or principle code', function (string $key, string $value) {
    $principal = Principal::factory()->create(['name' => 'Acme']);
    $product = Product::factory()->create([
        'principal_id' => $principal->id,
        'internal_code' => 'INT-1',
        'product_acc_code' => 'PR-1',
        'name' => 'Analyzer',
    ]);
    $order = Order::factory()->create();
    $existing = OrderItem::factory()->create(['order_id' => $order->id]);
    $row = [
        'principle' => 'Acme', 'internal_code' => '', 'principle_code' => '',
        'item_description' => 'Analyzer', 'qty' => 3, 'unit_price' => 1250,
    ];
    $row[$key] = $value;
    $import = new OrderItemsImport;
    $import->collection(new Collection([collect($row)]));

    $item = $import->items()[0];
    expect($order->orderItems()->count())->toBe(1)
        ->and($item)->toMatchArray([
            'principal_id' => $principal->id,
            'item_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 1250.0,
            'current_price' => 1250.0,
            'subtotal' => 3750.0,
        ])
        ->and($existing->exists)->toBeTrue();
})->with([
    'Internal Code' => ['internal_code', 'INT-1'],
    'Principle Code' => ['principle_code', 'PR-1'],
]);

test('uses row product principal when the principal name cell is blank', function () {
    $principal = Principal::factory()->create(['name' => 'Acme']);
    $product = Product::factory()->create([
        'principal_id' => $principal->id,
        'product_acc_code' => 'PR-1',
        'name' => 'Analyzer',
    ]);
    $import = new OrderItemsImport;

    $import->collection(new Collection([
        collect(['principle' => '', 'principle_code' => 'PR-1', 'item_description' => 'Analyzer', 'qty' => 2, 'unit_price' => 500]),
    ]));

    expect($import->items())->toHaveCount(1)
        ->and($import->items()[0]['principal_id'])->toBe($principal->id)
        ->and($import->items()[0]['item_id'])->toBe($product->id);
});

test('reports invalid rows and keeps valid import rows', function () {
    $principal = Principal::factory()->create(['name' => 'Acme']);
    Product::factory()->create([
        'principal_id' => $principal->id,
        'internal_code' => 'INT-1',
        'product_acc_code' => 'PR-1',
        'name' => 'Analyzer',
    ]);
    $import = new OrderItemsImport;

    $import->collection(new Collection([
        collect(['principle' => 'Acme', 'internal_code' => 'INT-1', 'qty' => 2, 'unit_price' => 500]),
        collect(['principle' => 'Unknown', 'internal_code' => 'missing', 'qty' => 1, 'unit_price' => 100]),
    ]));

    expect($import->items())->toHaveCount(1)
        ->and($import->failures())->toHaveCount(1);
});

test('reads the provided spreadsheet headers and skips blank lines', function () {
    $principal = Principal::factory()->create(['name' => 'Acme']);
    $product = Product::factory()->create([
        'principal_id' => $principal->id,
        'product_acc_code' => 'PR-1',
        'name' => 'Analyzer',
    ]);
    $import = new OrderItemsImport;
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([
        ['No', 'Principle', 'No DO/DN', 'Internal Code', 'Principle Code', 'Item Description', 'Qty', 'Uom', 'Unit Price'],
        [1, 'Acme', null, 'INT-1', 'PR-1', 'Analyzer', 3, 'Unit', 1250],
        [null, null, null, null, null, null, null, null, null],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'order-items-').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    try {
        Excel::import($import, $path);
    } finally {
        @unlink($path);
    }

    expect($import->items())->toHaveCount(1)
        ->and($import->items()[0]['item_id'])->toBe($product->id);
});
