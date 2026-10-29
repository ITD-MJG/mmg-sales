<?php

namespace Tests\Feature;

use App\Filament\Widgets\CustomerLeadsWidget;
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

function customerLeadsWidget(Customer $customer)
{
    return livewire(CustomerLeadsWidget::class, ['record' => $customer]);
}

it('lists only the leads that belong to the customer', function () {
    $customerLead = Lead::factory()->create([
        'customer_id' => $this->customer->id,
        'title' => 'Customer lead',
    ]);
    $otherLead = Lead::factory()->create(['title' => 'Other customer lead']);

    customerLeadsWidget($this->customer)
        ->assertCanSeeTableRecords([$customerLead])
        ->assertCanNotSeeTableRecords([$otherLead]);
});

it('shows only the lead code, title, status, expected revenue, and closing date', function () {
    customerLeadsWidget($this->customer)
        ->assertTableColumnExists('lead_code')
        ->assertTableColumnExists('title')
        ->assertTableColumnExists('status')
        ->assertTableColumnExists('estimated_revenue')
        ->assertTableColumnExists('expected_closing_date')
        ->assertTableColumnDoesNotExist('priority')
        ->assertTableColumnDoesNotExist('creator.name')
        ->assertTableColumnDoesNotExist('created_at');
});

it('labels the lead code column as a hash', function () {
    $lead = Lead::factory()->create(['customer_id' => $this->customer->id]);

    customerLeadsWidget($this->customer)
        ->assertTableColumnStateSet('lead_code', $lead->lead_code, $lead)
        ->assertSee('#');
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

    customerLeadsWidget($this->customer)
        ->filterTable('status', 'converted')
        ->assertCanSeeTableRecords([$converted])
        ->assertCanNotSeeTableRecords([$new]);
});

it('soft deletes a lead of the customer', function () {
    $lead = Lead::factory()->create(['customer_id' => $this->customer->id]);

    customerLeadsWidget($this->customer)
        ->callTableAction('delete', $lead)
        ->assertHasNoTableActionErrors();

    expect($lead->fresh()->trashed())->toBeTrue();
});
