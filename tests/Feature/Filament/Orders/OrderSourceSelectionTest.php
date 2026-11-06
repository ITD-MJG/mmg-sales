<?php

namespace Tests\Feature\Filament\Orders;

use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Lead;
use App\Models\Principal;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * CreateOrder is a Filament resource page, so it needs a user who may reach the
 * Orders resource. Without a seeded role the component never mounts and
 * Livewire::test() hands back a null instance instead of failing loudly.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('manual source permits manual selection from Sales and Marketing departments', function () {
    $sales = Department::factory()->create(['name' => 'Sales', 'code' => 'SAL']);
    $marketing = Department::factory()->create(['name' => 'Marketing', 'code' => 'MKT']);
    $finance = Department::factory()->create(['name' => 'Finance', 'code' => 'FNA']);
    $salesUser = User::factory()->create(['department_id' => $sales->id]);
    $marketingUser = User::factory()->create(['department_id' => $marketing->id]);
    $financeUser = User::factory()->create(['department_id' => $finance->id]);
    // Sales/Marketing actors are offered both departments' salespeople; anyone
    // else only ever sees Sales, so the actor has to sit in Sales here.
    $actor = User::factory()->create(['department_id' => $sales->id]);
    $actor->assignRole('Super Admin');

    Livewire::actingAs($actor)
        ->test(CreateOrder::class)
        ->assertFormFieldExists('source_record_id')
        ->assertFormFieldExists('sales_id', function ($field) use ($salesUser, $marketingUser, $financeUser) {
            $options = $field->getOptions();

            return isset($options[$salesUser->id], $options[$marketingUser->id])
                && ! isset($options[$financeUser->id]);
        });
});

test('selecting an owned lead prefills customer, source details, products, creator and collaborators', function () {
    $sales = Department::factory()->create(['name' => 'Sales', 'code' => 'SAL']);
    $marketing = Department::factory()->create(['name' => 'Marketing', 'code' => 'MKT']);
    // The lead picker only offers leads the acting user created, so the
    // creator IS the actor; the collaborator stays a separate user.
    $actor = User::factory()->create(['department_id' => $sales->id]);
    $actor->assignRole('Super Admin');
    $creator = $actor;
    $collaborator = User::factory()->create(['department_id' => $marketing->id]);
    $customer = Customer::factory()->create();
    $principal = Principal::factory()->create(['is_active' => true]);
    $product = Product::factory()->create([
        'principal_id' => $principal->id,
        'unit_price' => 125000,
    ]);
    $lead = Lead::factory()->create([
        'created_by' => $creator->id,
        'customer_id' => $customer->id,
        'notes' => 'Lead notes',
    ]);
    $lead->collaborators()->attach($collaborator->id, ['added_by' => $creator->id]);
    $lead->products()->attach($product->id, ['principal_id' => $principal->id, 'quantity' => 2, 'unit_price' => 125000]);

    $component = Livewire::actingAs($actor)->test(CreateOrder::class);

    $component->set('data.order_source', 'leads')
        ->set('data.source_record_id', $lead->id)
        ->assertFormSet([
            'end_customer_id' => $customer->id,
            'notes' => 'Lead notes',
            'sales_id' => $creator->id,
            'orderItems' => [[
                'principal_id' => $principal->id,
                'principal_name' => $principal->name,
                'item_id' => $product->id,
                'quantity' => 2,
                'price_type' => 'unit_price',
                'unit_price' => 125000,
                'current_price' => 125000,
                'discount_amount' => 0,
                'discount_percent' => 0,
                'subtotal' => 250000,
            ]],
        ])
        ->assertFormFieldExists('source_record_id');
});

test('changing the source or clearing its record resets source-populated fields for manual entry', function () {
    $actor = User::factory()->create();
    $actor->assignRole('Super Admin');
    $customer = Customer::factory()->create();
    $lead = Lead::factory()->create(['customer_id' => $customer->id, 'created_by' => $actor->id]);

    $component = Livewire::actingAs($actor)->test(CreateOrder::class);

    $component->set('data.order_source', 'leads')
        ->set('data.source_record_id', $lead->id)
        ->set('data.order_source', 'manual')
        ->assertFormSet([
            'source_record_id' => null,
            'end_customer_id' => null,
            'notes' => null,
            'sales_id' => null,
            'orderItems' => [],
        ]);
});

test('users outside Sales and Marketing default to no sales and only see Sales users', function () {
    $sales = Department::factory()->create(['name' => 'Sales', 'code' => 'SAL']);
    $marketing = Department::factory()->create(['name' => 'Marketing', 'code' => 'MKT']);
    $finance = Department::factory()->create(['name' => 'Finance', 'code' => 'FNA']);
    $salesUser = User::factory()->create(['name' => 'Sales User', 'department_id' => $sales->id]);
    User::factory()->create(['name' => 'Marketing User', 'department_id' => $marketing->id]);
    $actor = User::factory()->create(['department_id' => $finance->id]);
    $actor->assignRole('Super Admin');

    Livewire::actingAs($actor)
        ->test(CreateOrder::class)
        ->assertFormSet(['sales_id' => null])
        ->assertFormFieldExists('sales_id', fn ($field) => $field->getOptions() === [$salesUser->id => $salesUser->name]);
});
