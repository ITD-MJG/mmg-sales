<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
});

test('orders table shows the stored net sales amount', function () {
    $user = User::factory()->create();
    // Without a role the panel denies the list and Filament has no table to
    // assert against; the sibling order-list tests authorize the same way.
    $user->assignRole('Super Admin');
    $order = Order::factory()->create([
        'created_by' => $user->id,
        'net_sales_total' => null,
        'total_amount' => 98765,
    ]);

    Livewire::actingAs($user)
        ->test(ListOrders::class)
        ->assertTableColumnStateSet('total_amount', 98765, $order);
});
