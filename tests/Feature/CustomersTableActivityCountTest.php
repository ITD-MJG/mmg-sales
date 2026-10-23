<?php

namespace Tests\Feature;

use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\Activity;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('customers table shows activity counts without type and city columns', function () {
    $customer = Customer::factory()->create([
        'type' => 'hospital_clinic',
        'city' => 'Jakarta',
    ]);

    Activity::factory()->count(2)->create(['customer_id' => $customer->id]);
    $otherCustomer = Customer::factory()->create();
    Activity::factory()->create(['customer_id' => $otherCustomer->id]);

    Livewire::test(ListCustomers::class)
        ->assertCanSeeTableRecords([$customer, $otherCustomer])
        ->assertTableColumnStateSet('activities_count', 2, $customer)
        ->assertTableColumnStateSet('activities_count', 1, $otherCustomer)
        ->assertDontSee('Hospital/Apothecary/Clinic')
        ->assertTableColumnExists('activities_count')
        ->assertTableColumnDoesNotExist('type')
        ->assertTableColumnDoesNotExist('city')
        ->assertDontSee('Jakarta');
});
