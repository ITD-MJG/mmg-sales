<?php

use App\Models\Lead;
use App\Models\Principal;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

it('stores expected revenue and selected products on a lead', function () {
    $lead = Lead::factory()->create(['estimated_revenue' => 1250000.50]);
    $products = Product::factory()->count(2)->create();

    $lead->products()->sync($products->modelKeys());

    expect($lead->fresh()->estimated_revenue)->toBe('1250000.50')
        ->and($lead->products()->pluck('products.id')->all())->toEqualCanonicalizing($products->modelKeys())
        ->and(Schema::hasTable('lead_product'))->toBeTrue();
});

it('stores all assigned users as collaborators and preserves the creator', function () {
    $creator = User::factory()->create();
    $users = User::factory()->count(2)->create();
    $lead = Lead::factory()->create(['created_by' => $creator->id]);

    $lead->collaborators()->syncWithPivotValues($users->modelKeys(), ['added_by' => $creator->id]);

    expect($lead->fresh()->created_by)->toBe($creator->id)
        ->and($lead->collaborators()->pluck('users.id')->all())->toEqualCanonicalizing($users->modelKeys())
        ->and(Schema::hasColumn('leads', 'assigned_to'))->toBeFalse();
});

it('stores the principal selected for each product on a lead', function () {
    $principal = Principal::factory()->create();
    $product = Product::factory()->create(['principal_id' => $principal->id]);
    $lead = Lead::factory()->create();

    $leadProduct = $lead->leadProductItems()->create([
        'principal_id' => $principal->id,
        'product_id' => $product->id,
    ]);

    expect($leadProduct->fresh()->principal_id)->toBe($principal->id)
        ->and($leadProduct->fresh()->product_id)->toBe($product->id);
});

it('rejects a product that does not belong to its selected principal', function () {
    $principal = Principal::factory()->create();
    $otherPrincipal = Principal::factory()->create();
    $product = Product::factory()->create(['principal_id' => $principal->id]);

    $validator = Validator::make([
        'principal_id' => $otherPrincipal->id,
        'product_id' => $product->id,
    ], [
        'principal_id' => ['required', 'exists:principals,id'],
        'product_id' => [
            'required',
            'exists:products,id',
            function (string $attribute, mixed $value, Closure $fail) use ($otherPrincipal): void {
                if (Product::query()->whereKey($value)->where('principal_id', $otherPrincipal->id)->doesntExist()) {
                    $fail('The selected product does not belong to this principal.');
                }
            },
        ],
    ]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('product_id'))->toBeTrue();
});
