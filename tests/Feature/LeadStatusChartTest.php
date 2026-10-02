<?php

use App\Filament\Widgets\LeadStatusChart;
use App\Filament\Widgets\OpportunityStatusChart;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

use function Pest\Laravel\seed;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
});

/** Read the chart options the widget hands to ApexCharts. */
function leadStatusOptions(User $user): array
{
    Auth::login($user);

    $widget = new LeadStatusChart;
    $method = new ReflectionMethod($widget, 'getOptions');
    $method->setAccessible(true);

    $options = $method->invoke($widget);

    Auth::logout();

    return $options;
}

function salesStaff(): User
{
    $user = User::factory()->create();
    $user->assignRole('Sales Staff');

    return $user;
}

it('renders as a funnel chart', function () {
    $options = leadStatusOptions(salesStaff());

    expect($options['chart']['type'])->toBe('funnel');
});

it('fixes the top-to-bottom order at New, Contacted, Converted, Disqualified', function () {
    $options = leadStatusOptions(salesStaff());

    // Series order is the render order for a funnel, so this array *is* the
    // top-to-bottom reading.
    expect($options['labels'])->toBe(['New', 'Contacted', 'Converted', 'Disqualified']);
});

it('wraps the counts in a named series, which a bar chart requires', function () {
    // A funnel is ApexCharts' bar variant and reads `series[i][j]`. A flat
    // array of numbers renders nothing.
    $options = leadStatusOptions(salesStaff());

    expect($options['series'])->toBe([['name' => 'Leads', 'data' => [0, 0, 0, 0]]]);
});

it('gives each band its own colour and names the legend from the categories', function () {
    $options = leadStatusOptions(salesStaff());

    // Without `distributed`, every band takes the first colour.
    expect($options['plotOptions']['bar']['distributed'])->toBeTrue()
        ->and($options['colors'])->toHaveCount(4)
        // A bar's legend reads the x-axis categories, not `labels`.
        ->and($options['xaxis']['categories'])->toBe(['New', 'Contacted', 'Converted', 'Disqualified']);
});

it('keeps the declared band order even when a later status is largest', function () {
    $staff = salesStaff();

    Lead::factory()->count(2)->create(['status' => 'new', 'created_by' => $staff->id]);
    Lead::factory()->count(9)->create(['status' => 'disqualified', 'created_by' => $staff->id]);

    $options = leadStatusOptions($staff);

    // Labels stay in declared order; the values follow them, so index 1 (New)
    // holds the smaller count and index 3 (Disqualified) the larger.
    expect($options['labels'])->toBe(['New', 'Contacted', 'Converted', 'Disqualified'])
        ->and($options['series'][0]['data'])->toBe([2, 0, 0, 9]);
});

it('counts leads by status', function () {
    $staff = salesStaff();

    Lead::factory()->count(3)->create(['status' => 'new', 'created_by' => $staff->id]);
    Lead::factory()->count(4)->create(['status' => 'contacted', 'created_by' => $staff->id]);
    Lead::factory()->count(2)->create(['status' => 'converted', 'created_by' => $staff->id]);
    Lead::factory()->count(1)->create(['status' => 'disqualified', 'created_by' => $staff->id]);

    $options = leadStatusOptions($staff);

    expect($options['series'][0]['data'])->toBe([3, 4, 2, 1]);
});

it('scopes the counts to the staff member own leads', function () {
    $staff = salesStaff();

    $other = User::factory()->create();
    $other->assignRole('Sales Staff');

    Lead::factory()->count(2)->create(['status' => 'new', 'created_by' => $staff->id]);
    Lead::factory()->count(5)->create(['status' => 'new', 'created_by' => $other->id]);

    $options = leadStatusOptions($staff);

    expect(array_sum($options['series'][0]['data']))->toBe(2);
});

it('counts every lead for a super admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $other = salesStaff();
    Lead::factory()->count(6)->create(['status' => 'new', 'created_by' => $other->id]);

    $options = leadStatusOptions($admin);

    expect(array_sum($options['series'][0]['data']))->toBe(6);
});

it('is visible to everyone, unlike the chart it replaced', function () {
    expect(LeadStatusChart::canView())->toBeTrue();
});

it('renders on the dashboard for sales staff', function () {
    $staff = salesStaff();
    $this->actingAs($staff);

    Lead::factory()->count(2)->create(['status' => 'new', 'created_by' => $staff->id]);

    Livewire::test(LeadStatusChart::class)->assertOk();
});

it('hides the opportunity status chart from sales staff', function () {
    $this->actingAs(salesStaff());

    expect(OpportunityStatusChart::canView())->toBeFalse();
});

it('shows the opportunity status chart to a super admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);

    expect(OpportunityStatusChart::canView())->toBeTrue();
});
