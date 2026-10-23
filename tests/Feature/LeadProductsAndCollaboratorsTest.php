<?php

use App\Models\Lead;
use App\Models\Principal;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

it('stores lead product quantity and unit price', function () {
    $principal = Principal::factory()->create();
    $product = Product::factory()->create(['principal_id' => $principal->id]);
    $lead = Lead::factory()->create();

    $leadProduct = $lead->leadProductItems()->create([
        'principal_id' => $principal->id,
        'product_id' => $product->id,
        'quantity' => 3,
        'unit_price' => 12500,
    ]);

    expect($leadProduct->fresh()->quantity)->toBe(3)
        ->and((float) $leadProduct->fresh()->unit_price)->toBe(12500.0);
});

it('validates a product selection without requiring a principal field', function () {
    $principal = Principal::factory()->create();
    $product = Product::factory()->create(['principal_id' => $principal->id]);

    $validator = Validator::make(
        ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 1000],
        [
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
        ],
    );

    expect($validator->passes())->toBeTrue();
});
