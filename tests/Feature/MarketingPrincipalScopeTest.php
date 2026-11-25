<?php

use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Principal;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('matches leads by assigned principal and matches nothing for an empty list', function () {
    $assigned = Principal::factory()->create();
    $other = Principal::factory()->create();
    $assignedProduct = Product::factory()->create(['principal_id' => $assigned->id]);

    $matching = Lead::factory()->create();
    $matching->leadProductItems()->create([
        'product_id' => $assignedProduct->id,
        'principal_id' => $assigned->id,
        'quantity' => 1,
        'unit_price' => 1000,
    ]);
    Lead::factory()->create();

    expect(Lead::query()->forPrincipals([$assigned->id])->pluck('id')->all())->toEqual([$matching->id])
        ->and(Lead::query()->forPrincipals([$other->id])->pluck('id')->all())->toBe([])
        ->and(Lead::query()->forPrincipals([])->count())->toBe(0)
        ->and($matching->hasAnyAssignedPrincipal([$assigned->id]))->toBeTrue()
        ->and($matching->hasAnyAssignedPrincipal([$other->id]))->toBeFalse();
});

it('matches orders by header principal and by line principal', function () {
    $assigned = Principal::factory()->create();
    $other = Principal::factory()->create();

    $headerOrder = Order::factory()->create(['principal_id' => $assigned->id]);
    $lineOrder = Order::factory()->create(['principal_id' => $other->id]);
    OrderItem::factory()->create([
        'order_id' => $lineOrder->id,
        'item_id' => Product::factory()->create(['principal_id' => $assigned->id])->id,
    ]);
    $unrelated = Order::factory()->create(['principal_id' => $other->id]);

    expect(Order::query()->forPrincipals([$assigned->id])->pluck('id')->all())->toEqualCanonicalizing([$headerOrder->id, $lineOrder->id])
        ->and(Order::query()->forPrincipals([])->count())->toBe(0)
        ->and($headerOrder->hasAnyAssignedPrincipal([$assigned->id]))->toBeTrue()
        ->and($unrelated->hasAnyAssignedPrincipal([$assigned->id]))->toBeFalse();
});

it('scopes products by principal and matches nothing for an empty list', function () {
    $assigned = Principal::factory()->create();
    $product = Product::factory()->create(['principal_id' => $assigned->id]);
    Product::factory()->create();

    expect(Product::query()->forPrincipals([$assigned->id])->pluck('id')->all())->toEqual([$product->id])
        ->and(Product::query()->forPrincipals([])->count())->toBe(0);
});
