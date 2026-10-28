<?php

use App\Filament\Resources\Orders\Schemas\OrderForm;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);


test('order money parser preserves decimal values in localized and stored formats', function () {
    $parser = new ReflectionMethod(OrderForm::class, 'parseMoney');

    expect($parser->invoke(null, '1.234,56'))->toBe(1234.56)
        ->and($parser->invoke(null, '4100000.00'))->toBe(4100000.0);
});

test('line discount is stored as a currency amount and subtotal is net of discount', function () {
    $set = [];
    $get = [
        'item_id' => null,
        'quantity' => 2,
        'price_type' => 'unit_price',
        'unit_price' => '125.000,00',
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
        'unit_price' => '125.000,00',
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
    $get['unit_price'] = '100.000,00';
    $get['discount_percent'] = 0;
    $method->invoke(null, $setState, $getState);
    expect($set['subtotal'])->toBe(300000.0);

    $get['discount_percent'] = 25;
    $method->invoke(null, $setState, $getState);
    expect($set['subtotal'])->toBe(225000.0);
});
