<?php

use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Schemas\OrderForm;
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
