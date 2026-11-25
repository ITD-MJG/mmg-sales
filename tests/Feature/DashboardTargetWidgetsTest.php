<?php

namespace Tests\Feature;

use App\Filament\Widgets\InvoiceVsTargetChart;
use App\Filament\Widgets\SalesKpiWidget;
use App\Filament\Widgets\SalesTargetWidget;
use App\Filament\Widgets\TargetVsAchievementChart;
use App\Models\Order;
use App\Models\PaymentStatus;
use App\Models\Target;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

    $this->rep = User::factory()->create(['name' => 'Rep']);
    $this->importer = User::factory()->create(['name' => 'Importer']);

    Target::create(['user_id' => $this->rep->id, 'year' => 2026, 'month' => 6, 'monthly_target' => 1000]);
});

afterEach(function () {
    $this->travelBack();
});

/**
 * @return array<string, Stat>
 */
function targetStats(): array
{
    $stats = (function (): array {
        return $this->getStats();
    })->call(app(SalesTargetWidget::class));

    return collect($stats)->keyBy(fn (Stat $stat): string => (string) $stat->getLabel())->all();
}

it('credits the rep who sold the order, not the administrator who imported it', function () {
    // Every imported row carries the importer in `created_by` while `sales_id`
    // holds the rep, so scoring the creator column reported 0% for every rep.
    Order::factory()->create([
        'sales_id' => $this->rep->id,
        'created_by' => $this->importer->id,
        'order_date' => '2026-06-10',
        'total_amount' => 500,
    ]);

    actingAs($this->rep);

    expect(targetStats()['Target Achievement']->getValue())
        ->toBe('50.0% monthly / 50.0% annual');
});

it('does not credit the importer with revenue they only loaded', function () {
    Order::factory()->create([
        'sales_id' => $this->rep->id,
        'created_by' => $this->importer->id,
        'order_date' => '2026-06-10',
        'total_amount' => 500,
    ]);

    actingAs($this->importer);

    expect(targetStats()['Target Achievement']->getValue())
        ->toBe('0.0% monthly / 0.0% annual');
});

it('counts an order whether or not it has been marked shipped', function () {
    // `delivery_statuses` may hold no rows at all; achievement must not depend
    // on a delivery record existing.
    Order::factory()->create([
        'sales_id' => $this->rep->id,
        'order_date' => '2026-06-10',
        'total_amount' => 1000,
    ]);

    actingAs($this->rep);

    expect(targetStats()['Target Achievement']->getValue())
        ->toBe('100.0% monthly / 100.0% annual');
});

it('plots the rep own revenue against the target rows in millions', function () {
    Order::factory()->create([
        'sales_id' => $this->rep->id,
        'order_date' => '2026-06-10',
        'total_amount' => 1_500_000,
    ]);

    actingAs($this->rep);

    $data = (function (): array {
        return $this->getData();
    })->call(app(TargetVsAchievementChart::class));

    $revenue = $data['datasets'][0]['data'];
    $targets = $data['datasets'][1]['data'];

    expect($data['labels'])->toHaveCount(12)
        ->and($data['labels'][11])->toBe('Jun 2026')
        // Revenue and target share one axis, so both are reported in millions.
        ->and($revenue[11])->toBe(1.5)
        // A 1 000 rupiah target is 0.001 million.
        ->and($targets[11])->toBe(0.001)
        ->and($data['datasets'][1]['type'])->toBe('line');
});

it('leaves another rep orders out of the target chart', function () {
    $otherRep = User::factory()->create();

    Order::factory()->create([
        'sales_id' => $otherRep->id,
        'order_date' => '2026-06-10',
        'total_amount' => 9_000_000,
    ]);

    actingAs($this->rep);

    $data = (function (): array {
        return $this->getData();
    })->call(app(TargetVsAchievementChart::class));

    expect(array_sum($data['datasets'][0]['data']))->toBe(0.0);
});

it('counts a partially paid order as invoiced', function () {
    $order = Order::factory()->create([
        'sales_id' => $this->rep->id,
        'order_date' => '2026-06-10',
        'total_amount' => 1_500_000,
    ]);

    PaymentStatus::create([
        'order_id' => $order->id,
        'status' => 'partial',
        'amount' => 500_000,
        'effective_date' => '2026-06-12',
    ]);

    actingAs($this->rep);

    $data = (function (): array {
        return $this->getData();
    })->call(app(InvoiceVsTargetChart::class));

    expect($data['labels'])->toHaveCount(12)
        ->and($data['datasets'][0]['label'])->toBe('Invoice (Rp juta)')
        // The order's net sales count in full once any payment lands.
        ->and($data['datasets'][0]['data'][11])->toBe(1.5);
});

it('leaves unpaid orders out of the invoice chart', function () {
    Order::factory()->create([
        'sales_id' => $this->rep->id,
        'order_date' => '2026-06-10',
        'total_amount' => 9_000_000,
    ]);

    actingAs($this->rep);

    $data = (function (): array {
        return $this->getData();
    })->call(app(InvoiceVsTargetChart::class));

    expect(array_sum($data['datasets'][0]['data']))->toBe(0.0);
});

it('renders both target widgets without error', function () {
    actingAs($this->rep);

    expect(fn () => targetStats())->not->toThrow(Exception::class);
    expect(app(SalesKpiWidget::class))->toBeInstanceOf(SalesKpiWidget::class);
});
