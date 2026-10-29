<?php

namespace Tests\Feature;

use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\LeadsRelationManager;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('Super Admin');
    actingAs($this->user);
    $this->customer = Customer::factory()->create();
});

function customerLeadsRelationManager(Customer $customer, string $pageClass = ViewCustomer::class)
{
    return livewire(LeadsRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => $pageClass,
    ]);
}

it('lists only the leads that belong to the customer', function () {
    $customerLead = Lead::factory()->create([
        'customer_id' => $this->customer->id,
        'title' => 'Customer lead',
    ]);
    $otherLead = Lead::factory()->create(['title' => 'Other customer lead']);

    customerLeadsRelationManager($this->customer)
        ->assertCanSeeTableRecords([$customerLead])
        ->assertCanNotSeeTableRecords([$otherLead]);
});

it('filters the customer leads by status', function () {
    $converted = Lead::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'converted',
    ]);
    $new = Lead::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'new',
    ]);

    customerLeadsRelationManager($this->customer)
        ->filterTable('status', 'converted')
        ->assertCanSeeTableRecords([$converted])
        ->assertCanNotSeeTableRecords([$new]);
});

it('soft deletes a lead of the customer', function () {
    $lead = Lead::factory()->create(['customer_id' => $this->customer->id]);

    // Relation managers are read-only on View pages, so the delete action is
    // only reachable from the Edit page.
    customerLeadsRelationManager($this->customer, EditCustomer::class)
        ->callTableAction('delete', $lead)
        ->assertHasNoTableActionErrors();

    expect($lead->fresh()->trashed())->toBeTrue();
});
