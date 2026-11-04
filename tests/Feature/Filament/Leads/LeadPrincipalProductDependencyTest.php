<?php

namespace Tests\Feature\Filament\Leads;

use App\Filament\Resources\Leads\Pages\CreateLead;
use App\Models\Lead;
use App\Models\Principal;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
});

it('binds the principal and unit price from the selected product', function () {
    $principal = Principal::factory()->create();
    $product = Product::factory()->create([
        'principal_id' => $principal->id,
        'is_active' => true,
        'unit_price' => 15000,
    ]);

    livewire(CreateLead::class)
        ->fillForm(['leadProductItems' => [['product_id' => $product->id]]])
        ->assertFormSet([
            'leadProductItems.0.principal_id' => $principal->id,
            'leadProductItems.0.unit_price' => 15000,
        ]);
});

it('does not guess a product when only a principal is selected', function () {
    $principal = Principal::factory()->create();
    Product::factory()->create([
        'principal_id' => $principal->id,
        'is_active' => true,
    ]);

    livewire(CreateLead::class)
        ->fillForm(['leadProductItems' => [['principal_id' => $principal->id]]])
        ->assertFormSet([
            'leadProductItems.0.product_id' => null,
        ]);
});

it('clears the product when the principal is switched away from it', function () {
    $firstPrincipal = Principal::factory()->create();
    $secondPrincipal = Principal::factory()->create();
    $product = Product::factory()->create([
        'principal_id' => $firstPrincipal->id,
        'is_active' => true,
        'unit_price' => 15000,
    ]);

    livewire(CreateLead::class)
        ->fillForm(['leadProductItems' => [['product_id' => $product->id]]])
        ->fillForm(['leadProductItems' => [['principal_id' => $secondPrincipal->id]]])
        ->assertFormSet([
            'leadProductItems.0.product_id' => null,
            'leadProductItems.0.unit_price' => null,
            'leadProductItems.0.line_total' => 0,
        ]);
});

it('keeps the product when its principal is reselected', function () {
    $principal = Principal::factory()->create();
    $product = Product::factory()->create([
        'principal_id' => $principal->id,
        'is_active' => true,
        'unit_price' => 15000,
    ]);

    livewire(CreateLead::class)
        ->fillForm(['leadProductItems' => [['product_id' => $product->id]]])
        ->fillForm(['leadProductItems' => [['principal_id' => $principal->id]]])
        ->assertFormSet([
            'leadProductItems.0.product_id' => $product->id,
            'leadProductItems.0.principal_id' => $principal->id,
        ]);
});

it('persists principal_id on the lead product pivot', function () {
    $principal = Principal::factory()->create();
    $product = Product::factory()->create([
        'principal_id' => $principal->id,
        'is_active' => true,
        'unit_price' => 15000,
    ]);

    $lead = Lead::factory()->create();
    $lead->leadProductItems()->create([
        'product_id' => $product->id,
        'principal_id' => $principal->id,
        'quantity' => 1,
        'unit_price' => 15000,
    ]);

    expect($lead->leadProductItems()->first()->principal_id)->toBe($principal->id);
});
