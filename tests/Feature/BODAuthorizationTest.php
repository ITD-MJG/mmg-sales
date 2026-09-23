<?php

use App\Models\Customer;
use App\Models\Department;
use App\Models\Lead;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // A read-only global viewer: Director sitting in the Management department.
    $management = Department::factory()->create(['name' => Department::MANAGEMENT]);
    $this->bodUser = User::factory()->create(['department_id' => $management->id]);
    $this->bodUser->assignRole('Management Director');
});

it('allows a Management Director to view resources but denies create/update/delete', function () {
    $lead = Lead::factory()->create();
    $customer = Customer::factory()->create();

    // Test Lead Authorization
    expect($this->bodUser->can('viewAny', Lead::class))->toBeTrue();
    expect($this->bodUser->can('view', $lead))->toBeTrue();
    expect($this->bodUser->can('create', Lead::class))->toBeFalse();
    expect($this->bodUser->can('update', $lead))->toBeFalse();
    expect($this->bodUser->can('delete', $lead))->toBeFalse();

    // Test Customer Authorization
    expect($this->bodUser->can('viewAny', Customer::class))->toBeTrue();
    expect($this->bodUser->can('view', $customer))->toBeTrue();
    expect($this->bodUser->can('create', Customer::class))->toBeFalse();
    expect($this->bodUser->can('update', $customer))->toBeFalse();
    expect($this->bodUser->can('delete', $customer))->toBeFalse();

    // Test Order Authorization
    expect($this->bodUser->can('viewAny', Order::class))->toBeTrue();
    expect($this->bodUser->can('create', Order::class))->toBeFalse();
});
