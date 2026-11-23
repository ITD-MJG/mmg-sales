<?php

namespace Tests\Feature\Filament\Leads;

use App\Filament\Resources\Leads\Pages\CreateLead;
use App\Filament\Resources\Leads\Pages\EditLead;
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

function productWithPrice(float $unitPrice): Product
{
    $principal = Principal::factory()->create();

    return Product::factory()->create([
        'principal_id' => $principal->id,
        'is_active' => true,
        'unit_price' => $unitPrice,
    ]);
}

function leadWithLine(float $estimatedRevenue): Lead
{
    $product = productWithPrice(10000);
    $lead = Lead::factory()->create(['estimated_revenue' => $estimatedRevenue]);

    $lead->leadProductItems()->create([
        'product_id' => $product->id,
        'principal_id' => $product->principal_id,
        'quantity' => 2,
        'unit_price' => 10000,
    ]);

    return $lead;
}

/**
 * Hydrated repeater items are keyed by UUID, so the line has to be addressed
 * by its real key instead of position.
 */
function setFirstLineQuantity($component, int $quantity): void
{
    $key = array_key_first($component->get('data.leadProductItems'));

    $component->set("data.leadProductItems.{$key}.quantity", $quantity);
}

it('fills expected revenue from the products total', function () {
    $product = productWithPrice(15000);

    livewire(CreateLead::class)
        ->fillForm(['leadProductItems' => [['product_id' => $product->id]]])
        ->fillForm(['leadProductItems' => [['quantity' => 3]]])
        ->assertFormSet([
            'total_value' => 45000,
            'estimated_revenue' => 45000,
        ]);
});

it('keeps following the products total while it has not been adjusted', function () {
    $product = productWithPrice(10000);

    livewire(CreateLead::class)
        ->fillForm(['leadProductItems' => [['product_id' => $product->id]]])
        ->fillForm(['leadProductItems' => [['quantity' => 2]]])
        ->assertFormSet(['estimated_revenue' => 20000])
        ->fillForm(['leadProductItems' => [['quantity' => 5]]])
        ->assertFormSet(['estimated_revenue' => 50000]);
});

it('leaves manually adjusted expected revenue alone when products change', function () {
    $product = productWithPrice(10000);

    livewire(CreateLead::class)
        ->fillForm(['estimated_revenue' => 7500])
        ->fillForm(['leadProductItems' => [['product_id' => $product->id]]])
        ->fillForm(['leadProductItems' => [['quantity' => 4]]])
        ->assertFormSet([
            'total_value' => 40000,
            'estimated_revenue' => 7500,
        ]);
});

it('treats a stored expected revenue that differs from the products total as manual', function () {
    $lead = leadWithLine(estimatedRevenue: 12345);

    $component = livewire(EditLead::class, ['record' => $lead->getKey()])
        ->assertFormSet(['estimated_revenue' => 12345]);

    setFirstLineQuantity($component, 3);

    $component->assertFormSet([
        'total_value' => 30000,
        'estimated_revenue' => 12345,
    ]);
});

it('keeps an existing lead in sync while its stored expected revenue matches the products total', function () {
    $lead = leadWithLine(estimatedRevenue: 20000);

    $component = livewire(EditLead::class, ['record' => $lead->getKey()]);

    setFirstLineQuantity($component, 3);

    $component->assertFormSet([
        'total_value' => 30000,
        'estimated_revenue' => 30000,
    ]);
});
