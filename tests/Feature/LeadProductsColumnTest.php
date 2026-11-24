<?php

namespace Tests\Feature;

use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Models\Lead;
use App\Models\LeadProduct;
use App\Models\Principal;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Super Admin');
    actingAs($user);

    $this->lead = Lead::factory()->create(['created_by' => $user->id]);
    $this->principal = Principal::factory()->create(['name' => 'PT Sumber Rejeki']);
});

function addProductLine(Lead $lead, Principal $principal, string $name, int $quantity, float $unitPrice): LeadProduct
{
    $product = Product::factory()->create(['name' => $name, 'principal_id' => $principal->id]);

    return LeadProduct::create([
        'lead_id' => $lead->id,
        'product_id' => $product->id,
        'principal_id' => $principal->id,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
    ]);
}

it('counts product lines rather than the units across them', function () {
    addProductLine($this->lead, $this->principal, 'Centrifuge Tube', 50, 10000);
    addProductLine($this->lead, $this->principal, 'Petri Dish', 25, 20000);
    addProductLine($this->lead, $this->principal, 'Gloves', 5, 50000);

    livewire(ListLeads::class)
        ->assertTableColumnExists('lead_product_items_count')
        // Three lines, even though the quantities add up to eighty.
        ->assertTableColumnStateSet('lead_product_items_count', 3, $this->lead);
});

/**
 * Render what the column click opens: the table's own action, resolved by the
 * name the column carries, with the record the row is showing.
 */
function productModalHtml(Lead $lead): string
{
    $table = livewire(ListLeads::class)->instance()->getTable();
    $action = $table->getAction('products');
    $action->record($lead);

    expect($action)->not->toBeNull();

    return (string) $action->getModalContent();
}

it('mounts the column action from the table', function () {
    // The column only carries an action *name*: it clicks through to
    // mountTableAction(), so the name has to resolve on the table itself.
    $component = livewire(ListLeads::class)
        ->mountTableAction('products', (string) $this->lead->getKey());

    expect($component->instance()->mountedActions)->toHaveCount(1);
});

it('lists the product lines with a price, a line total and a summed total', function () {
    addProductLine($this->lead, $this->principal, 'Centrifuge Tube', 2, 150000);
    addProductLine($this->lead, $this->principal, 'Petri Dish', 3, 250000);

    $html = productModalHtml($this->lead->fresh());

    expect($html)
        ->toContain('Centrifuge Tube')
        ->toContain('Petri Dish')
        ->toContain('PT Sumber Rejeki')
        ->toContain('Qty')
        ->toContain('Unit Price')
        // 2 × 150.000 and 3 × 250.000, then the summed 1.050.000 underneath.
        ->toContain('300.000')
        ->toContain('750.000')
        ->toContain('1.050.000');
});

it('names the lead it is listing products for', function () {
    $table = livewire(ListLeads::class)->instance()->getTable();
    $action = $table->getAction('products');
    $action->record($this->lead);

    expect($action->getModalHeading())->toBe('Products on '.$this->lead->lead_code);
});

it('shows an empty state for a lead without products', function () {
    expect(productModalHtml($this->lead))->toContain('No products linked to this lead.');
});

it('offers the same list from the row actions, which is where it can be mounted', function () {
    livewire(ListLeads::class)
        ->assertTableActionVisible('products', $this->lead);
});
