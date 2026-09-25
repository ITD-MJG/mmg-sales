<?php

use App\DTOs\ReportFilterData;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Principal;
use App\Models\User;
use App\Services\Reports\SalesReportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function createTerritory(string $name = 'Test Territory'): object
{
    $id = DB::table('territories')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);

    return (object) ['id' => $id, 'name' => $name];
}

function makeFilters(array $overrides = []): ReportFilterData
{
    return new ReportFilterData(...array_merge([
        'startDate' => Carbon::now()->startOfMonth(),
        'endDate' => Carbon::now()->endOfMonth(),
    ], $overrides));
}

it('returns revenue by principal from order_items', function () {
    $genolution = Principal::factory()->create(['name' => 'Genolution']);
    $genesystem = Principal::factory()->create(['name' => 'Genesystem']);

    $customer = Customer::factory()->create(['type' => 'hospital_clinic']);
    $territory = createTerritory();
    $user = User::factory()->create(['territory_id' => $territory->id]);

    $order1 = Order::factory()->create([
        'created_by' => $user->id,
        'end_customer_id' => $customer->id,
        'order_date' => Carbon::now()->format('Y-m-d'),
    ]);
    OrderItem::factory()->create([
        'order_id' => $order1->id,
        'principal_id' => $genolution->id,
        'subtotal' => 100000,
    ]);

    $order2 = Order::factory()->create([
        'created_by' => $user->id,
        'end_customer_id' => $customer->id,
        'order_date' => Carbon::now()->format('Y-m-d'),
    ]);
    OrderItem::factory()->create([
        'order_id' => $order2->id,
        'principal_id' => $genesystem->id,
        'subtotal' => 200000,
    ]);

    $service = new SalesReportService;
    $result = $service->generate(makeFilters());

    expect($result->revenueByPrincipal)->not->toBeEmpty();
    $names = $result->revenueByPrincipal->pluck('name')->toArray();
    expect($names)->toContain('Genolution');
    expect($names)->toContain('Genesystem');

    $genolutionRevenue = $result->revenueByPrincipal->firstWhere('name', 'Genolution')['revenue'];
    expect($genolutionRevenue)->toBe(100000.0);
});

it('returns revenue by sales rep from order_items', function () {
    $territory = createTerritory();
    $user = User::factory()->create(['territory_id' => $territory->id]);
    $customer = Customer::factory()->create(['type' => 'hospital_clinic']);

    $order = Order::factory()->create([
        'created_by' => $user->id,
        'sales' => [$user->id],
        'end_customer_id' => $customer->id,
        'order_date' => Carbon::now()->format('Y-m-d'),
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'subtotal' => 300000,
    ]);

    $service = new SalesReportService;
    $result = $service->generate(makeFilters());

    expect($result->revenueBySalesRep)->not->toBeEmpty();
    $rep = $result->revenueBySalesRep->firstWhere('name', $user->name);
    expect($rep)->not->toBeNull();
    expect($rep['revenue'])->toBe(300000.0);
});

it('returns revenue by territory from order_items', function () {
    $territory = createTerritory('Jakarta');
    $user = User::factory()->create(['territory_id' => $territory->id]);
    $customer = Customer::factory()->create(['type' => 'hospital_clinic']);

    $order = Order::factory()->create([
        'created_by' => $user->id,
        'sales' => [$user->id],
        'end_customer_id' => $customer->id,
        'order_date' => Carbon::now()->format('Y-m-d'),
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'subtotal' => 750000,
    ]);

    $service = new SalesReportService;
    $result = $service->generate(makeFilters());

    expect($result->revenueByTerritory)->not->toBeEmpty();
    $territoryResult = $result->revenueByTerritory->firstWhere('name', 'Jakarta');
    expect($territoryResult)->not->toBeNull();
    expect($territoryResult['revenue'])->toBe(750000.0);
});

it('returns revenue by customer group from order_items', function () {
    $group = CustomerGroup::factory()->create(['name' => 'Hospital']);
    $customer = Customer::factory()->create(['customer_group_id' => $group->id, 'type' => 'hospital_clinic']);
    $territory = createTerritory();
    $user = User::factory()->create(['territory_id' => $territory->id]);

    $order = Order::factory()->create([
        'created_by' => $user->id,
        'end_customer_id' => $customer->id,
        'order_date' => Carbon::now()->format('Y-m-d'),
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'subtotal' => 150000,
    ]);

    $service = new SalesReportService;
    $result = $service->generate(makeFilters());

    expect($result->revenueByCustomerGroup)->not->toBeEmpty();
    $groupResult = $result->revenueByCustomerGroup->firstWhere('name', 'Hospital');
    expect($groupResult)->not->toBeNull();
    expect($groupResult['revenue'])->toBe(150000.0);
});

it('calculates totalGrossSales from order_items subtotal', function () {
    $territory = createTerritory();
    $user = User::factory()->create(['territory_id' => $territory->id]);
    $customer = Customer::factory()->create(['type' => 'hospital_clinic']);

    $order = Order::factory()->create([
        'created_by' => $user->id,
        'end_customer_id' => $customer->id,
        'order_date' => Carbon::now()->format('Y-m-d'),
        'total_amount' => 100000,
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'subtotal' => 500000,
    ]);

    $service = new SalesReportService;
    $result = $service->generate(makeFilters());

    expect($result->totalGrossSales)->toBe(500000.0);
});

it('excludes orders outside date range', function () {
    $territory = createTerritory();
    $user = User::factory()->create(['territory_id' => $territory->id]);
    $customer = Customer::factory()->create(['type' => 'hospital_clinic']);

    $orderInRange = Order::factory()->create([
        'created_by' => $user->id,
        'end_customer_id' => $customer->id,
        'order_date' => Carbon::now()->format('Y-m-d'),
    ]);
    OrderItem::factory()->create([
        'order_id' => $orderInRange->id,
        'subtotal' => 100000,
    ]);

    $orderOutOfRange = Order::factory()->create([
        'created_by' => $user->id,
        'end_customer_id' => $customer->id,
        'order_date' => Carbon::now()->subMonths(3)->format('Y-m-d'),
    ]);
    OrderItem::factory()->create([
        'order_id' => $orderOutOfRange->id,
        'subtotal' => 200000,
    ]);

    $service = new SalesReportService;
    $result = $service->generate(makeFilters());

    expect($result->totalGrossSales)->toBe(100000.0);
});
