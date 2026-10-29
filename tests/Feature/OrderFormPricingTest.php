<?php

use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('order form renders amount fields without money masks', function () {
    $actor = User::factory()->create();
    $this->seed(RolesAndPermissionsSeeder::class);
    $actor->assignRole('Super Admin');

    $html = Livewire::actingAs($actor)
        ->test(CreateOrder::class)
        ->html();

    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//input[@*[name()="x-mask:dynamic"]]'))->toHaveCount(0);
});

test('line discount is stored as a currency amount and subtotal is net of discount', function () {
    $set = [];
    $get = [
        'item_id' => null,
        'quantity' => 2,
        'price_type' => 'unit_price',
        'unit_price' => 125000,
        'discount_percent' => 10,
    ];

    $setState = function (string $key, mixed $value) use (&$set): void {
        $set[$key] = $value;
    };
    $getState = fn (string $key) => $get[$key] ?? $set[$key] ?? null;
    $method = new ReflectionMethod(OrderForm::class, 'updateLineTotal');
    $method->invoke(null, $setState, $getState);

    expect($set['discount_amount'])->toBe(25000.0)
        ->and($set['subtotal'])->toBe(225000.0);
});
test('line total updates after product price, quantity, and discount changes', function () {
    $set = [];
    $get = [
        'item_id' => null,
        'quantity' => 2,
        'unit_price' => 125000,
        'discount_percent' => 10,
    ];
    $setState = function (string $key, mixed $value) use (&$set): void {
        $set[$key] = $value;
    };
    $getState = function (string $key) use (&$get, &$set) {
        return $get[$key] ?? $set[$key] ?? null;
    };
    $method = new ReflectionMethod(OrderForm::class, 'updateLineTotal');

    $method->invoke(null, $setState, $getState);
    expect($set['subtotal'])->toBe(225000.0);

    $get['quantity'] = 3;
    $get['unit_price'] = 100000;
    $get['discount_percent'] = 0;
    $method->invoke(null, $setState, $getState);
    expect($set['subtotal'])->toBe(300000.0);

    $get['discount_percent'] = 25;
    $method->invoke(null, $setState, $getState);
    expect($set['subtotal'])->toBe(225000.0);
});

test('product selection callback recalculates order totals from parent repeater state', function () {
    $get = [
        'quantity' => 1,
        'unit_price' => 125000,
        'discount_percent' => 0,
        '../' => [
            'first' => ['subtotal' => 125000],
            'second' => ['subtotal' => 50000],
        ],
        '../../discount_on' => 10,
    ];
    $set = [];
    $setState = function (string $key, mixed $value) use (&$set): void {
        $set[$key] = $value;
    };
    $getState = fn (string $key) => $get[$key] ?? null;

    $updateLineTotal = new ReflectionMethod(OrderForm::class, 'updateLineTotal');
    $calculateOrderTotals = new ReflectionMethod(OrderForm::class, 'calculateOrderTotals');
    $updateLineTotal->invoke(null, $setState, $getState);
    $calculateOrderTotals->invoke(null, $setState, (float) $get['../../discount_on'], $get['../']);

    expect($set['subtotal'])->toBe(175000.0)
        ->and($set['total_amount'])->toBe(157500.0);
});

test('recalculates order totals from the complete repeater state for a line-item change', function () {
    $set = [];
    $get = [
        'orderItems' => [
            'first' => [
                'quantity' => 2,
                'unit_price' => 125000,
                'discount_percent' => 10,
                'subtotal' => 225000,
            ],
            'second' => [
                'quantity' => 1,
                'unit_price' => 50000,
                'discount_percent' => 0,
                'subtotal' => 50000,
            ],
        ],
        'discount_on' => 10,
        'subtotal' => 0,
    ];

    $setState = function (string $key, mixed $value) use (&$set): void {
        $set[$key] = $value;
    };
    $calculateOrderTotals = new ReflectionMethod(OrderForm::class, 'calculateOrderTotals');
    $getState = function (string $key) use (&$get, &$set) {
        return match ($key) {
            'orderItems' => $get['orderItems'],
            'subtotal' => $set['subtotal'] ?? $get[$key] ?? null,
            default => $get[$key] ?? null,
        };
    };
    $calculateOrderTotals->invoke(null, $setState, 10.0, $get['orderItems']);

    $get['orderItems']['first']['quantity'] = 3;
    $get['orderItems']['first']['subtotal'] = 337500.0;
    $getState = function (string $key) use (&$get, &$set) {
        return match ($key) {
            'orderItems' => $get['orderItems'],
            'subtotal' => $set['subtotal'] ?? $get[$key] ?? null,
            default => $get[$key] ?? $set[$key] ?? null,
        };
    };
    $calculateOrderTotals->invoke(null, $setState, 10.0, $get['orderItems']);

    expect($set['subtotal'])->toBe(387500.0)
        ->and($set['total_amount'])->toBe(348750.0)
        ->and($set['net_sales_total'])->toBe(348750.0);
});

test('line totals stay per item while order totals sum every line total', function () {
    $actor = User::factory()->create();
    $this->seed(RolesAndPermissionsSeeder::class);
    $actor->assignRole('Super Admin');

    $productA = Product::factory()->create(['unit_price' => 100000]);
    $productB = Product::factory()->create(['unit_price' => 50000]);

    $component = Livewire::actingAs($actor)
        ->test(CreateOrder::class)
        ->fillForm([
            'orderItems' => [
                [
                    'item_id' => $productA->id,
                    'quantity' => 2,
                    'price_type' => 'unit_price',
                    'unit_price' => 100000,
                    'discount_percent' => 10,
                ],
                [
                    'item_id' => $productB->id,
                    'quantity' => 1,
                    'price_type' => 'unit_price',
                    'unit_price' => 50000,
                    'discount_percent' => 0,
                ],
            ],
            'discount_on' => 10,
        ]);

    $items = array_values($component->get('data.orderItems'));

    expect((float) $items[0]['subtotal'])->toBe(180000.0)
        ->and((float) $items[1]['subtotal'])->toBe(50000.0);

    $component->assertFormSet([
        'subtotal' => 230000,
        'total_amount' => 207000,
        'net_sales_total' => 207000,
    ]);

    $component->set('data.orderItems.0.quantity', 3);

    $items = array_values($component->get('data.orderItems'));

    expect((float) $items[0]['subtotal'])->toBe(270000.0)
        ->and((float) $items[1]['subtotal'])->toBe(50000.0);

    $component->assertFormSet([
        'subtotal' => 320000,
        'total_amount' => 288000,
        'net_sales_total' => 288000,
    ]);

    $component->set('data.orderItems', [$items[0]]);

    $component->assertFormSet([
        'subtotal' => 270000,
        'total_amount' => 243000,
        'net_sales_total' => 243000,
    ]);
});

test('order discount changes net sales without touching any line total', function () {
    $actor = User::factory()->create();
    $this->seed(RolesAndPermissionsSeeder::class);
    $actor->assignRole('Super Admin');

    $product = Product::factory()->create(['unit_price' => 100000]);

    $component = Livewire::actingAs($actor)
        ->test(CreateOrder::class)
        ->fillForm([
            'orderItems' => [
                [
                    'item_id' => $product->id,
                    'quantity' => 2,
                    'price_type' => 'unit_price',
                    'unit_price' => 100000,
                    'discount_percent' => 0,
                ],
            ],
            'discount_on' => 0,
        ]);

    $component->assertFormSet([
        'subtotal' => 200000,
        'total_amount' => 200000,
    ]);

    $component->set('data.discount_on', 25);

    $items = array_values($component->get('data.orderItems'));

    expect((float) $items[0]['subtotal'])->toBe(200000.0);

    $component->assertFormSet([
        'subtotal' => 200000,
        'total_amount' => 150000,
        'net_sales_total' => 150000,
    ]);
});
