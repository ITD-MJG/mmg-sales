<?php

namespace Tests\Feature\Filament\Orders;

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    actingAs($user);

    $this->order = Order::factory()->create();
});

it('keeps the totals section sticky beside the product list', function () {
    Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
        ->assertSeeHtml('lg:sticky')
        ->assertSeeHtml('lg:self-start')
        ->assertSeeHtml('lg:top-20');
});
