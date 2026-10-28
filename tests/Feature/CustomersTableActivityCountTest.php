<?php

namespace Tests\Feature;

use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\Activity;
use App\Models\ActivityComment;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('customers table shows activity counts without type and city columns', function () {
    $customer = Customer::factory()->create([
        'type' => 'hospital_clinic',
        'city' => 'Jakarta',
    ]);

    $customerActivities = Activity::factory()->count(2)->create(['customer_id' => $customer->id]);
    ActivityComment::factory()->count(3)->create(['activity_id' => $customerActivities->first()->id]);
    $otherCustomer = Customer::factory()->create();
    $otherActivities = Activity::factory()->count(1)->create(['customer_id' => $otherCustomer->id]);
    ActivityComment::factory()->create(['activity_id' => $otherActivities->first()->id]);

    Livewire::test(ListCustomers::class)
        ->assertCanSeeTableRecords([$customer, $otherCustomer])
        ->assertTableColumnStateSet('activities_count', 2, $customer)
        ->assertTableColumnStateSet('activities_count', 1, $otherCustomer)
        ->assertTableColumnStateSet('activities_comments_count', 3, $customer)
        ->assertTableColumnStateSet('activities_comments_count', 1, $otherCustomer)
        ->assertDontSee('Hospital/Apothecary/Clinic')
        ->assertTableColumnExists('activities_count')
        ->assertTableColumnExists('activities_comments_count')
        ->assertTableColumnDoesNotExist('type')
        ->assertTableColumnDoesNotExist('city')
        ->assertDontSee('Jakarta');
});
