<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\RevenueByTerritoryChart;
use App\Filament\Widgets\TopVisitedCustomersChart;
use App\Filament\Widgets\TopVisitedCustomersWidget;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Lead;
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
 * Render the widget and pull the chart payload out of it.
 *
 * @return array{labels: array<int, string>, datasets: array<int, array<string, mixed>>}
 */
function chartData(): array
{
    $component = livewire(TopVisitedCustomersChart::class)->assertOk();

    return (function (): array {
        return $this->getData();
    })->call($component->instance());
}

/**
 * Log activities against a customer, optionally through a lead.
 */
function logActivities(Customer $customer, int $count, ?User $user = null, ?Lead $lead = null): void
{
    Activity::factory()->count($count)->create([
        'customer_id' => $customer->id,
        'lead_id' => $lead?->id,
        'user_id' => ($user ?? auth()->user())->id,
        'performed_at' => now(),
    ]);
}

it('uses a three column dashboard layout', function () {
    expect((new Dashboard)->getColumns())->toBe(3);
});

it('does not register the revenue by territory chart on the dashboard', function () {
    expect((new Dashboard)->getWidgets())
        ->not->toContain(RevenueByTerritoryChart::class);
});

it('registers the top visited customers chart on the dashboard', function () {
    expect((new Dashboard)->getWidgets())->toContain(TopVisitedCustomersChart::class);
});

it('does not register the superseded top visited customers table widget', function () {
    expect((new Dashboard)->getWidgets())
        ->not->toContain(TopVisitedCustomersWidget::class);
});

it('renders the top visited customers chart', function () {
    livewire(TopVisitedCustomersChart::class)
        ->assertSee('Top Visited Customers')
        ->assertOk();
});

it('ranks customers by activity count, highest first, and excludes activities without a customer', function () {
    $busy = Customer::factory()->create(['name' => 'Busy Customer']);
    $quiet = Customer::factory()->create(['name' => 'Quiet Customer']);

    logActivities($busy, 3);
    logActivities($quiet, 1);

    Activity::factory()->create([
        'customer_id' => null,
        'user_id' => $this->user->id,
        'performed_at' => now(),
    ]);

    $data = chartData();

    // Highest first, and no ->reverse(): index 0 renders at the top of a
    // horizontal bar chart.
    expect($data['labels'])->toBe(['Busy Customer', 'Quiet Customer'])
        ->and($data['datasets'][0]['data'])->toBe([3, 1]);
});

it('labels bars with the customer name, not the lead title', function () {
    $customer = Customer::factory()->create(['name' => 'RSUP Wahidin Sudirohusodo']);
    $lead = Lead::factory()->create([
        'title' => 'Petridish Pekybio',
        'customer_id' => $customer->id,
    ]);

    logActivities($customer, 2, lead: $lead);

    $labels = chartData()['labels'];

    expect($labels)->toBe(['RSUP Wahidin Sudirohusodo'])
        ->and($labels[0])->not->toContain('Petridish Pekybio');
});

it('aggregates every lead of a customer into one bar', function () {
    $customer = Customer::factory()->create(['name' => 'PT Biofarma']);

    $firstLead = Lead::factory()->create(['title' => 'Tecan Tips', 'customer_id' => $customer->id]);
    $secondLead = Lead::factory()->create(['title' => 'Scan RDI', 'customer_id' => $customer->id]);

    logActivities($customer, 2, lead: $firstLead);
    logActivities($customer, 3, lead: $secondLead);

    $data = chartData();

    expect($data['labels'])->toBe(['PT Biofarma'])
        ->and($data['datasets'][0]['data'])->toBe([5]);
});

it('still labels a customer that has been soft deleted', function () {
    $customer = Customer::factory()->create(['name' => 'Balai Besar Lab Kes']);
    logActivities($customer, 3);

    $customer->delete();

    expect(chartData()['labels'])->toBe(['Balai Besar Lab Kes']);
});

it('limits the chart to the ten most active customers', function () {
    Customer::factory()->count(12)->create()->each(function (Customer $customer): void {
        logActivities($customer, 1);
    });

    expect(chartData()['labels'])->toHaveCount(10);
});

it('scopes the chart to the activities the user may see', function () {
    $otherUser = User::factory()->create();
    $otherUser->assignRole('Sales Staff');

    $mine = Customer::factory()->create(['name' => 'My Customer']);
    $theirs = Customer::factory()->create(['name' => 'Their Customer']);

    logActivities($mine, 1);
    logActivities($theirs, 5, user: $otherUser);

    // The Super Admin in beforeEach sees everything, so assert the scope from
    // the other user's side: their own activity is all they get.
    actingAs($otherUser);

    $labels = chartData()['labels'];

    expect($labels)->toBe(['Their Customer']);
});
