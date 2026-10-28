<?php

namespace Tests\Feature;

use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\Activity;
use App\Models\ActivityComment;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('Super Admin');
    actingAs($this->user);
});

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
        ->assertTableColumnStateSet('activity_comments_count', 3, $customer)
        ->assertTableColumnStateSet('activity_comments_count', 1, $otherCustomer)
        ->assertDontSee('Hospital/Apothecary/Clinic')
        ->assertTableColumnExists('activities_count')
        ->assertTableColumnExists('activity_comments_count')
        ->assertTableColumnDoesNotExist('type')
        ->assertTableColumnDoesNotExist('city')
        ->assertDontSee('Jakarta');
});
