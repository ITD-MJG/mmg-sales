<?php

namespace Tests\Feature\Filament\Orders;

use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Principal;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('manual source permits manual selection from Sales and Marketing departments', function () {
    $sales = Department::factory()->create(['name' => 'Sales', 'code' => 'SAL']);
    $marketing = Department::factory()->create(['name' => 'Marketing', 'code' => 'MKT']);
    $finance = Department::factory()->create(['name' => 'Finance', 'code' => 'FNA']);
    $salesUser = User::factory()->create(['department_id' => $sales->id]);
    $marketingUser = User::factory()->create(['department_id' => $marketing->id]);
    $financeUser = User::factory()->create(['department_id' => $finance->id]);
    $actor = User::factory()->create();

    Livewire::actingAs($actor)
        ->test(CreateOrder::class)
        ->assertFormFieldExists('source_record_id')
        ->assertFormFieldIsNotRequired('source_record_id')
        ->assertFormFieldExists('sales_id', function ($field) use ($salesUser, $marketingUser, $financeUser) {
            $options = $field->getOptions();

            return isset($options[$salesUser->id], $options[$marketingUser->id])
                && ! isset($options[$financeUser->id]);
        });
});

test('selecting an owned lead prefills customer, source details, products, creator and collaborators', function () {
    $sales = Department::factory()->create(['name' => 'Sales', 'code' => 'SAL']);
    $marketing = Department::factory()->create(['name' => 'Marketing', 'code' => 'MKT']);
    $creator = User::factory()->create(['department_id' => $sales->id]);
    $collaborator = User::factory()->create(['department_id' => $marketing->id]);
    $actor = User::factory()->create();
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
                'item_id' => $product->id,
                'quantity' => 2,
                'price_type' => 'unit_price',
                'unit_price' => '125.000',
                'current_price' => '125.000',
                'subtotal' => '250.000',
            ]],
        ])
        ->assertFormFieldExists('source_record_id')
        ->assertFormFieldIsNotRequired('source_record_id');
});

test('changing the source or clearing its record resets source-populated fields for manual entry', function () {
    $actor = User::factory()->create();
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

test('selecting an opportunity prefills creator and collaborators in sales selector', function () {
    $sales = Department::factory()->create(['name' => 'Sales', 'code' => 'SAL']);
    $marketing = Department::factory()->create(['name' => 'Marketing', 'code' => 'MKT']);
    $creator = User::factory()->create(['department_id' => $sales->id]);
    $collaborator = User::factory()->create(['department_id' => $marketing->id]);
    $actor = User::factory()->create();
    $customer = Customer::factory()->create();
    $opportunity = Opportunity::factory()->create([
        'created_by' => $creator->id,
        'customer_id' => $customer->id,
        'notes' => 'Opportunity notes',
    ]);
    $opportunity->collaborators()->attach($collaborator->id, ['added_by' => $creator->id]);

    Livewire::actingAs($actor)
        ->test(CreateOrder::class)
        ->set('data.order_source', 'opportunities')
        ->set('data.source_record_id', $opportunity->id)
        ->assertFormSet([
            'end_customer_id' => $customer->id,
            'notes' => 'Opportunity notes',
            'sales_id' => $creator->id,
        ]);
});

test('users outside Sales and Marketing default to no sales and only see Sales users', function () {
    $sales = Department::factory()->create(['name' => 'Sales', 'code' => 'SAL']);
    $marketing = Department::factory()->create(['name' => 'Marketing', 'code' => 'MKT']);
    $finance = Department::factory()->create(['name' => 'Finance', 'code' => 'FNA']);
    $salesUser = User::factory()->create(['name' => 'Sales User', 'department_id' => $sales->id]);
    User::factory()->create(['name' => 'Marketing User', 'department_id' => $marketing->id]);
    $actor = User::factory()->create(['department_id' => $finance->id]);

    Livewire::actingAs($actor)
        ->test(CreateOrder::class)
        ->assertFormSet(['sales_id' => null])
        ->assertFormFieldExists('sales_id', fn ($field) => $field->getOptions() === [$salesUser->id => $salesUser->name]);
});
