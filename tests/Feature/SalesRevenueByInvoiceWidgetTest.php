<?php

use App\Filament\Pages\SalesDashboard;
use App\Filament\Widgets\SalesRevenueByInvoiceWidget;
use App\Models\Order;
use App\Models\PaymentStatus;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');
    $this->actingAs($this->admin);

    $order = Order::factory()->create([
        'sales_id' => $this->admin->id,
        'total_amount' => 1000,
        'order_date' => '2026-03-10',
    ]);

    PaymentStatus::create([
        'order_id' => $order->id,
        'status' => 'full',
        'amount' => 1000,
        'effective_date' => '2026-03-15',
    ]);
});

test('the widget renders its heading and invoice row', function () {
    Livewire::test(SalesRevenueByInvoiceWidget::class, [
        'pageFilters' => ['start_date' => '2026-03-01', 'end_date' => '2026-03-31'],
    ])
        ->assertSee('Sales Revenue (By Invoice)')
        ->assertSee('1.000');
});

test('selecting a group filters the table and clicking again clears it', function () {
    $component = Livewire::test(SalesRevenueByInvoiceWidget::class, [
        'pageFilters' => ['start_date' => '2026-03-01', 'end_date' => '2026-03-31'],
    ]);

    $key = (string) $this->admin->id;

    $component->call('selectGroup', $key)->assertSet('selectedKey', $key);
    $component->call('selectGroup', $key)->assertSet('selectedKey', null);
});

test('the sales dashboard hosts the widget with a date filter form', function () {
    Livewire::test(SalesDashboard::class)
        ->assertSee('Sales Revenue (By Invoice)')
        ->assertSee('Period');
});
