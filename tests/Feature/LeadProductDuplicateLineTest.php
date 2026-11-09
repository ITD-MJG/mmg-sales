<?php

use App\Filament\Resources\Leads\Pages\EditLead;
use App\Models\Lead;
use App\Models\Principal;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('rejects two lines with the same product instead of failing on the unique index', function () {
    $actor = User::factory()->create();
    $this->seed(RolesAndPermissionsSeeder::class);
    $actor->assignRole('Super Admin');

    $lead = Lead::factory()->create(['phone' => '+6281234567890']);
    $principal = Principal::factory()->create();
    $product = Product::factory()->create(['principal_id' => $principal->id]);

    Livewire::actingAs($actor)
        ->test(EditLead::class, ['record' => $lead->getRouteKey()])
        ->fillForm([
            'estimated_revenue' => 1000000,
            'expected_closing_date' => now()->addWeek()->toDateString(),
            'leadProductItems' => [
                [
                    'principal_id' => $principal->id,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 1000,
                ],
                [
                    'principal_id' => $principal->id,
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 1000,
                ],
            ],
        ])
        ->call('save')
        ->assertHasFormErrors([
            'leadProductItems.0.product_id',
            'leadProductItems.1.product_id',
        ]);

    expect($lead->leadProductItems()->count())->toBe(0);
});
