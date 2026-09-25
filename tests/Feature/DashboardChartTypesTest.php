<?php

use App\Filament\Widgets\LeadStatusChart;
use App\Filament\Widgets\MonthlyRevenueTrendChart;
use App\Filament\Widgets\RevenueByPrincipalChart;
use App\Filament\Widgets\TopSalesRepresentativeVisitsWidget;
use App\Filament\Widgets\TopSellingProductsChart;
use App\Filament\Widgets\TopVisitedCustomersChart;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Principal;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('Super Admin');
    actingAs($this->user);
});

/**
 * Read a widget's chart type and options without rendering it.
 *
 * @return array{type: string, options: array<string, mixed>}
 */
function chartSpec(string $widget): array
{
    $instance = app($widget);

    return [
        'type' => (function (): string {
            return $this->getType();
        })->call($instance),
        'options' => (function (): array {
            return $this->getOptions();
        })->call($instance),
    ];
}

it('renders lead status as a doughnut, since it is a share of the pipeline', function () {
    $spec = chartSpec(LeadStatusChart::class);

    expect($spec['type'])->toBe('doughnut')
        ->and($spec['options']['plugins']['legend']['display'])->toBeTrue();
});

it('keeps monthly revenue as a line, since it is a time series', function () {
    $spec = chartSpec(MonthlyRevenueTrendChart::class);

    expect($spec['type'])->toBe('line');
});

it('renders revenue by principal as a doughnut share', function () {
    $spec = chartSpec(RevenueByPrincipalChart::class);

    expect($spec['type'])->toBe('doughnut')
        ->and($spec['options']['plugins']['legend']['display'])->toBeTrue();
});

it('renders product ranking as horizontal bars', function () {
    $spec = chartSpec(TopSellingProductsChart::class);

    expect($spec['type'])->toBe('bar')
        ->and($spec['options']['indexAxis'])->toBe('y');
});

it('renders the customer ranking as horizontal bars', function () {
    $spec = chartSpec(TopVisitedCustomersChart::class);

    expect($spec['type'])->toBe('bar')
        ->and($spec['options']['indexAxis'])->toBe('y');
});

it('renders the sales rep ranking as horizontal bars', function () {
    $spec = chartSpec(TopSalesRepresentativeVisitsWidget::class);

    expect($spec['type'])->toBe('bar')
        ->and($spec['options']['indexAxis'])->toBe('y');
});

it('exposes no JS callbacks in chart options, which JSON encoding would strip', function () {
    $widgets = [
        LeadStatusChart::class,
        MonthlyRevenueTrendChart::class,
        RevenueByPrincipalChart::class,
        TopSellingProductsChart::class,
        TopVisitedCustomersChart::class,
        TopSalesRepresentativeVisitsWidget::class,
    ];

    foreach ($widgets as $widget) {
        $encoded = json_encode(chartSpec($widget)['options']);

        // Filament renders options through @js(), so any callback must survive
        // JSON encoding. A raw closure or "function () {}" string would not.
        expect($encoded)->not->toContain('function (')
            ->and(json_decode($encoded, true))->toBeArray();
    }
});

it('reports monthly revenue in millions with the unit named on the axis', function () {
    $order = Order::factory()->create([
        'created_by' => $this->user->id,
        'order_date' => now(),
        'total_amount' => 1_500_000_000,
    ]);

    expect($order->total_amount)->toBeNumeric();

    $instance = app(MonthlyRevenueTrendChart::class);
    $data = (function (): array {
        return $this->getData();
    })->call($instance);

    $options = chartSpec(MonthlyRevenueTrendChart::class)['options'];

    // 1.5bn rupiah reads as 1500 on the axis, with the unit stated once.
    expect(max($data['datasets'][0]['data']))->toBe(1500.0)
        ->and($data['datasets'][0]['label'])->toContain('juta')
        ->and($options['scales']['y']['title']['text'])->toBe('Rp juta');
});

it('trims long product names server-side so the axis stays readable', function () {
    $principal = Principal::factory()->create();
    // Built directly, not via ProductFactory: that factory still writes a
    // `sku` column the products table no longer has, so it throws.
    $product = Product::create([
        'name' => 'PK00128 - 50mL Centrifuge Tube with Screw Cap, Sterile, Bag of 500',
        'principal_id' => $principal->id,
        'unit_price' => 1000,
        'unit_of_measure' => 'Box',
    ]);

    $order = Order::factory()->create([
        'created_by' => $this->user->id,
        'order_date' => now(),
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'principal_id' => $principal->id,
        'quantity' => 5,
    ]);

    $instance = app(TopSellingProductsChart::class);
    $data = (function (): array {
        return $this->getData();
    })->call($instance);

    expect($data['labels'])->toHaveCount(1)
        ->and($data['labels'][0])->toStartWith('PK00128')
        ->and(mb_strlen($data['labels'][0]))->toBeLessThanOrEqual(20)
        ->and($data['datasets'][0]['data'])->toBe([5]);
});

it('aggregates the rep ranking per rep, not per rep-and-customer', function () {
    $rep = User::factory()->create(['name' => 'Febrian Vantoni']);
    $rep->assignRole('Sales Staff');

    $firstCustomer = Customer::factory()->create();
    $secondCustomer = Customer::factory()->create();

    Activity::factory()->count(2)->create([
        'user_id' => $rep->id,
        'customer_id' => $firstCustomer->id,
        'performed_at' => now(),
    ]);

    Activity::factory()->count(3)->create([
        'user_id' => $rep->id,
        'customer_id' => $secondCustomer->id,
        'performed_at' => now(),
    ]);

    $instance = app(TopSalesRepresentativeVisitsWidget::class);
    $data = (function (): array {
        return $this->getData();
    })->call($instance);

    // One bar for the rep, carrying the sum across both customers.
    expect($data['labels'])->toBe(['Febrian Vantoni'])
        ->and($data['datasets'][0]['data'])->toBe([5]);
});

it('renders every redesigned widget without error', function () {
    Lead::factory()->count(2)->create(['created_by' => $this->user->id]);

    foreach ([
        LeadStatusChart::class,
        MonthlyRevenueTrendChart::class,
        RevenueByPrincipalChart::class,
        TopSellingProductsChart::class,
        TopVisitedCustomersChart::class,
        TopSalesRepresentativeVisitsWidget::class,
    ] as $widget) {
        livewire($widget)->assertOk();
    }
});
