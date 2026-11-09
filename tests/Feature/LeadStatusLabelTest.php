<?php

namespace Tests\Feature;

use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Filament\Resources\Leads\Pages\ViewLead;
use App\Filament\Widgets\CustomerLeadsWidget;
use App\Filament\Widgets\PrincipalLeadsWidget;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadProduct;
use App\Models\Principal;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('Super Admin');
    Livewire::actingAs($this->user);
});

it('renders the status badge with the declared label, not the raw column value', function () {
    $lead = Lead::factory()->create(['created_by' => $this->user->id, 'status' => 'closed_won']);

    $html = Livewire::test(ListLeads::class)
        ->assertSuccessful()
        ->html();

    expect($html)->toContain('Closed (Won)')
        ->and($html)->not->toContain('Closed_won');
});

it('renders every declared status label in the table badge', function () {
    foreach (array_keys(Lead::STATUS_LABELS) as $status) {
        Lead::factory()->create(['created_by' => $this->user->id, 'status' => $status]);
    }

    $html = Livewire::test(ListLeads::class)
        ->assertSuccessful()
        ->html();

    foreach (Lead::STATUS_LABELS as $label) {
        expect($html)->toContain($label);
    }

    // Snake_case values must never reach the rendered badge text.
    expect($html)->not->toContain('closed_won')
        ->and($html)->not->toContain('in_process');
});

it('renders the status badge on the lead infolist with the declared label', function () {
    $lead = Lead::factory()->create(['created_by' => $this->user->id, 'status' => 'closed_lost']);

    $html = Livewire::test(ViewLead::class, ['record' => $lead->getRouteKey()])
        ->assertSuccessful()
        ->html();

    expect($html)->toContain('Closed (Lost)')
        ->and($html)->not->toContain('Closed_lost');
});

it('renders the declared status label on the customer leads widget', function () {
    $customer = Customer::factory()->create();
    Lead::factory()->create([
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
        'status' => 'in_process',
    ]);

    $html = Livewire::test(CustomerLeadsWidget::class, ['record' => $customer])
        ->assertSuccessful()
        ->html();

    expect($html)->toContain('In Process')
        ->and($html)->not->toContain('In_process');
});

it('renders the declared status label on the principal leads widget', function () {
    $principal = Principal::factory()->create();
    $product = Product::factory()->create(['principal_id' => $principal->id]);
    $lead = Lead::factory()->create(['created_by' => $this->user->id, 'status' => 'closed_lost']);

    LeadProduct::query()->create([
        'lead_id' => $lead->id,
        'product_id' => $product->id,
        'principal_id' => $principal->id,
        'quantity' => 1,
        'unit_price' => 100,
    ]);

    $html = Livewire::test(PrincipalLeadsWidget::class, ['record' => $principal])
        ->assertSuccessful()
        ->html();

    expect($html)->toContain('Closed (Lost)')
        ->and($html)->not->toContain('Closed_lost');
});
