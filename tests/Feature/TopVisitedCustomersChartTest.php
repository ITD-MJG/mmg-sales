<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\RevenueByTerritoryChart;
use App\Filament\Widgets\TopVisitedCustomersChart;
use App\Filament\Widgets\TopVisitedCustomersWidget;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
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
 * Log activities against a lead, optionally through a customer.
 */
function logActivities(Lead $lead, int $count, ?User $user = null): void
{
    Activity::factory()->count($count)->create([
        'lead_id' => $lead->id,
        'opportunity_id' => null,
        'customer_id' => $lead->customer_id,
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

it('registers the top visited leads chart on the dashboard', function () {
    expect((new Dashboard)->getWidgets())->toContain(TopVisitedCustomersChart::class);
});

it('does not register the superseded top visited customers table widget', function () {
    expect((new Dashboard)->getWidgets())
        ->not->toContain(TopVisitedCustomersWidget::class);
});

it('renders the top visited leads chart', function () {
    livewire(TopVisitedCustomersChart::class)
        ->assertSee('Top Visited Leads')
        ->assertOk();
});

it('ranks leads by activity count, highest first', function () {
    $busy = Lead::factory()->create(['lead_code' => 'LEAD-202601-0001']);
    $quiet = Lead::factory()->create(['lead_code' => 'LEAD-202601-0002']);
    $middling = Lead::factory()->create(['lead_code' => 'LEAD-202601-0003']);

    logActivities($busy, 3);
    logActivities($middling, 2);
    logActivities($quiet, 1);

    $data = chartData();

    // Highest first, and no ->reverse(): index 0 renders at the top of a
    // horizontal bar chart.
    expect($data['labels'])->toBe(['LEAD-202601-0001', 'LEAD-202601-0003', 'LEAD-202601-0002'])
        ->and($data['datasets'][0]['data'])->toBe([3, 2, 1]);
});

it('labels bars with the lead code, not the customer name or lead title', function () {
    $customer = Customer::factory()->create(['name' => 'RS Sehat Sentosa']);
    $lead = Lead::factory()->create([
        'lead_code' => 'LEAD-202602-0042',
        'title' => 'Petridish Pekybio',
        'customer_id' => $customer->id,
    ]);

    logActivities($lead, 2);

    $labels = chartData()['labels'];

    expect($labels)->toBe(['LEAD-202602-0042'])
        ->and($labels[0])->not->toContain('RS Sehat Sentosa')
        ->and($labels[0])->not->toContain('Petridish Pekybio');
});

it('falls back to the opportunity code when an activity links to an opportunity', function () {
    $opportunity = Opportunity::factory()->create(['opportunity_code' => 'LEAD-202603-0007']);

    Activity::factory()->count(4)->forOpportunity($opportunity)->create([
        'customer_id' => null,
        'user_id' => $this->user->id,
        'performed_at' => now(),
    ]);

    expect(chartData()['labels'])->toBe(['LEAD-202603-0007'])
        ->and(chartData()['datasets'][0]['data'])->toBe([4]);
});

it('aggregates every activity of one lead into a single bar', function () {
    $lead = Lead::factory()->create(['lead_code' => 'LEAD-202604-0009']);

    logActivities($lead, 2);
    logActivities($lead, 3);

    $data = chartData();

    expect($data['labels'])->toBe(['LEAD-202604-0009'])
        ->and($data['datasets'][0]['data'])->toBe([5]);
});

it('limits the chart to the ten most active leads', function () {
    Lead::factory()->count(12)->create()->each(function (Lead $lead, int $index): void {
        logActivities($lead, 1 + $index);
    });

    expect(chartData()['labels'])->toHaveCount(10);
});

it('scopes the chart to the activities the user may see', function () {
    $otherUser = User::factory()->create();
    $otherUser->assignRole('Sales Staff');

    $mine = Lead::factory()->create(['lead_code' => 'LEAD-202605-0001']);
    $theirs = Lead::factory()->create(['lead_code' => 'LEAD-202605-0002']);

    logActivities($mine, 1);
    logActivities($theirs, 5, user: $otherUser);

    // The Super Admin in beforeEach sees everything, so assert the scope from
    // the other user's side: their own activity is all they get.
    actingAs($otherUser);

    expect(chartData()['labels'])->toBe(['LEAD-202605-0002']);
});
