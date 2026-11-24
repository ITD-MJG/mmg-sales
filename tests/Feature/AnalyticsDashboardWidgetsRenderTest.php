<?php

namespace Tests\Feature;

use App\Filament\Widgets\Reports\RevenueByCustomerGroupWidget;
use App\Filament\Widgets\Reports\RevenueByPrincipalWidget;
use App\Filament\Widgets\Reports\RevenueTrendWidget;
use App\Filament\Widgets\Reports\SalesReportStatsWidget;
use App\Filament\Widgets\Reports\TopCustomersWidget;
use App\Filament\Widgets\Reports\TopSalesRepresentativesWidget;
use App\Filament\Widgets\Reports\TopSellingProductsWidget;
use App\Filament\Widgets\Reports\TopTerritoriesWidget;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Principal;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));
    seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    actingAs($admin);

    $this->filters = [
        'start_date' => now()->subMonths(11)->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
    ];

    $principal = Principal::factory()->create(['name' => 'Princ A']);
    $product = Product::factory()->create(['name' => 'Product A', 'principal_id' => $principal->id]);
    $customer = Customer::factory()->create(['name' => 'Cust A']);

    $order = Order::factory()->create([
        'sales_id' => $admin->id,
        'end_customer_id' => $customer->id,
        'order_date' => '2026-06-10',
        'total_amount' => 1000,
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'item_id' => $product->id,
        'principal_id' => $principal->id,
        'quantity' => 3,
        'subtotal' => 1000,
    ]);
});

afterEach(function () {
    $this->travelBack();
});

it('renders every reports widget mounted on the analytics dashboard', function (string $widget) {
    Livewire::test($widget, ['pageFilters' => $this->filters])->assertOk();
})->with([
    'stats' => SalesReportStatsWidget::class,
    'revenue trend' => RevenueTrendWidget::class,
    'sales reps' => TopSalesRepresentativesWidget::class,
    'territories' => TopTerritoriesWidget::class,
    'customer group' => RevenueByCustomerGroupWidget::class,
    'principal' => RevenueByPrincipalWidget::class,
    'products' => TopSellingProductsWidget::class,
    'customers' => TopCustomersWidget::class,
]);
