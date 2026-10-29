<?php

namespace Tests\Feature;

use App\Filament\Resources\Principals\PrincipalResource;
use App\Filament\Resources\Principals\Tables\PrincipalLeadsTable;
use App\Filament\Resources\Principals\Tables\PrincipalOrdersTable;
use App\Filament\Widgets\PrincipalLeadsWidget;
use App\Filament\Widgets\PrincipalOrdersWidget;
use App\Filament\Widgets\PrincipalStatsWidget;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Principal;
use App\Models\Product;
use App\Models\User;
use App\Services\PrincipalMetricsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('Super Admin');
    actingAs($this->user);

    $this->principal = Principal::factory()->create();
    $this->product = Product::factory()->create(['principal_id' => $this->principal->id]);
    $this->otherPrincipal = Principal::factory()->create();
    $this->otherProduct = Product::factory()->create(['principal_id' => $this->otherPrincipal->id]);
});

function principalLead(Principal $principal, array $attributes = []): Lead
{
    $lead = Lead::factory()->create($attributes);
    LeadProduct::query()->create([
        'lead_id' => $lead->id,
        'product_id' => $principal->products()->firstOrFail()->id,
        'principal_id' => $principal->id,
        'quantity' => 1,
        'unit_price' => 100,
    ]);

    return $lead;
}

it('includes leads whose product belongs to the principal and excludes other principals', function () {
    $lead = principalLead($this->principal);
    $otherLead = Lead::factory()->create();
    $otherLead->products()->attach($this->otherProduct->id);

    expect(PrincipalMetricsService::leadsQuery($this->principal)->pluck('id')->all())
        ->toBe([$lead->id]);
});

it('counts orders containing a principal product once and totals their principal line sales', function () {
    $customer = Customer::factory()->create();
    $order = Order::factory()->create([
        'end_customer_id' => $customer->id,
        'principal_id' => $this->otherPrincipal->id,
        'total_amount' => 1500,
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'item_id' => $this->product->id,
        'principal_id' => $this->principal->id,
        'subtotal' => 1200,
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'item_id' => $this->otherProduct->id,
        'principal_id' => $this->otherPrincipal->id,
        'subtotal' => 300,
    ]);

    $unrelatedOrder = Order::factory()->create([
        'principal_id' => $this->otherPrincipal->id,
        'total_amount' => 900,
    ]);
    OrderItem::factory()->create([
        'order_id' => $unrelatedOrder->id,
        'item_id' => $this->otherProduct->id,
        'principal_id' => $this->otherPrincipal->id,
        'subtotal' => 900,
    ]);

    expect(PrincipalMetricsService::ordersQuery($this->principal)->pluck('id')->all())
        ->toBe([$order->id]);

    expect(PrincipalMetricsService::totals($this->principal))
        ->toMatchArray([
            'orders' => 1,
            'actual_sales' => 1200.0,
            'leads' => 0,
            'expected_revenue' => 0.0,
        ]);
});

it('uses stored net sales for imported orders without line items', function () {
    Order::factory()->create([
        'principal_id' => $this->principal->id,
        'net_sales_total' => 1800,
        'total_amount' => 2000,
    ]);

    expect(PrincipalMetricsService::totals($this->principal)['actual_sales'])->toBe(1800.0);
});

it('sums expected revenue for all matching leads', function () {
    principalLead($this->principal, ['estimated_revenue' => 500]);
    principalLead($this->principal, ['estimated_revenue' => 750]);
    Lead::factory()->create(['estimated_revenue' => 2000]);

    expect(PrincipalMetricsService::totals($this->principal)['expected_revenue'])->toBe(1250.0);
});

it('scopes both table widgets to products belonging to the principal', function () {
    $lead = principalLead($this->principal);
    $otherLead = Lead::factory()->create();
    $otherLead->products()->attach($this->otherProduct->id);
    $order = Order::factory()->create();
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'item_id' => $this->product->id,
        'principal_id' => $this->principal->id,
    ]);
    $otherOrder = Order::factory()->create();
    OrderItem::factory()->create([
        'order_id' => $otherOrder->id,
        'item_id' => $this->otherProduct->id,
        'principal_id' => $this->otherPrincipal->id,
    ]);

    expect(PrincipalLeadsTable::query($this->principal)->pluck('id')->all())->toBe([$lead->id])
        ->and(PrincipalOrdersTable::query($this->principal)->pluck('id')->all())->toBe([$order->id]);
});

it('renders four principal totals and a two-column pair of table widgets', function () {
    $stats = new PrincipalStatsWidget;
    $stats->record = $this->principal;
    $getStats = new \ReflectionMethod($stats, 'getStats');

    expect($getStats->invoke($stats))->toHaveCount(4)
        ->and((new PrincipalLeadsWidget)->getColumnSpan())->toBe(1)
        ->and((new PrincipalOrdersWidget)->getColumnSpan())->toBe(1);
});

it('registers a working principal view page', function () {
    expect(PrincipalResource::getPages())->toHaveKey('view');

    $this->get(PrincipalResource::getUrl('view', ['record' => $this->principal]))
        ->assertSuccessful()
        ->assertSeeLivewire(PrincipalStatsWidget::class)
        ->assertSeeLivewire(PrincipalLeadsWidget::class)
        ->assertSeeLivewire(PrincipalOrdersWidget::class);
});
