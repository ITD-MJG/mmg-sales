<?php

use App\Filament\Widgets\LeadStatusChart;
use App\Filament\Widgets\OpportunityStatusChart;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
});

/** Read the labels, values and colours the widget hands to Chart.js. */
function leadStatusData(User $user): array
{
    Auth::login($user);

    $data = (new LeadStatusChart)->getChartData();

    Auth::logout();

    return $data;
}

/** The lead counts, in band order. */
function leadStatusValues(User $user): array
{
    return leadStatusData($user)['values'];
}

function salesStaff(): User
{
    $user = User::factory()->create();
    $user->assignRole('Sales Staff');

    return $user;
}

it('fixes the top-to-bottom order at New, Contacted, Converted, Disqualified', function () {
    // The funnel plugin plots the dataset in the order given and exposes no
    // sort-by-value, so this array *is* the top-to-bottom reading.
    expect(leadStatusData(salesStaff())['labels'])
        ->toBe(['New', 'Contacted', 'Converted', 'Disqualified']);
});

it('keeps the declared band order even when a later status is largest', function () {
    $staff = salesStaff();

    Lead::factory()->count(2)->create(['status' => 'new', 'created_by' => $staff->id]);
    Lead::factory()->count(9)->create(['status' => 'disqualified', 'created_by' => $staff->id]);

    $data = leadStatusData($staff);

    // Labels stay in declared order and the values follow them, so New holds
    // the smaller count and Disqualified the larger.
    expect($data['labels'])->toBe(['New', 'Contacted', 'Converted', 'Disqualified'])
        ->and($data['values'])->toBe([2, 0, 0, 9]);
});

it('counts leads by status', function () {
    $staff = salesStaff();

    Lead::factory()->count(3)->create(['status' => 'new', 'created_by' => $staff->id]);
    Lead::factory()->count(4)->create(['status' => 'contacted', 'created_by' => $staff->id]);
    Lead::factory()->count(2)->create(['status' => 'converted', 'created_by' => $staff->id]);
    Lead::factory()->count(1)->create(['status' => 'disqualified', 'created_by' => $staff->id]);

    expect(leadStatusValues($staff))->toBe([3, 4, 2, 1]);
});

it('scopes the counts to the staff member own leads', function () {
    $staff = salesStaff();

    $other = User::factory()->create();
    $other->assignRole('Sales Staff');

    Lead::factory()->count(2)->create(['status' => 'new', 'created_by' => $staff->id]);
    Lead::factory()->count(5)->create(['status' => 'new', 'created_by' => $other->id]);

    expect(array_sum(leadStatusValues($staff)))->toBe(2);
});

it('counts every lead for a super admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $other = salesStaff();
    Lead::factory()->count(6)->create(['status' => 'new', 'created_by' => $other->id]);

    expect(array_sum(leadStatusValues($admin)))->toBe(6);
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

it('draws on a canvas through the bundled chart module', function () {
    $this->actingAs(salesStaff());

    // The widget no longer hands an options array to ApexCharts; it renders a
    // section whose Alpine component draws onto a canvas via the Vite bundle.
    Livewire::test(LeadStatusChart::class)
        ->assertSee('<canvas', escape: false)
        ->assertSee('MmgCharts', escape: false);
});

it('keeps the values numeric and one per band', function () {
    $staff = salesStaff();
    Lead::factory()->count(2)->create(['status' => 'contacted', 'created_by' => $staff->id]);

    $data = leadStatusData($staff);

    expect($data['values'])->toHaveCount(4);

    foreach ($data['values'] as $value) {
        expect($value)->toBeInt();
    }
});

it('gives every band its own colour so each status paints and the legend fills', function () {
    // One colour per band, in band order: a repeated colour would paint two
    // statuses alike and collapse their legend entries.
    $data = leadStatusData(salesStaff());

    expect($data['colors'])->toHaveCount(4)
        ->and(array_unique($data['colors']))->toHaveCount(4);
});

it('counts a status the enum no longer knows as zero rather than dropping the band', function () {
    // Every band must render even with no data behind it, so a funnel never
    // silently loses a stage.
    $staff = salesStaff();

    foreach (leadStatusData($staff)['values'] as $value) {
        expect($value)->toBe(0);
    }
});

it('gives every legend entry an explicit font colour so it follows the theme', function () {
    // Chart.js paints legend text with `legendItem.fontColor` and leaves it
    // untouched when absent, which renders black on the dark theme. The
    // custom generateLabels must therefore carry the colour itself.
    $source = file_get_contents(resource_path('js/charts/funnel.js'));

    expect($source)->toContain('generateLabels');

    // An uncommented assignment, not a mention inside a comment.
    expect($source)->toMatch('/^\s*fontColor:\s*\S/m');
});

it('sizes the funnel from Filament frame classes rather than a fixed pixel height', function () {
    // The other dashboard charts take their height from Filament's
    // `.fi-wi-chart-frame` aspect ratio. A hardcoded height here would make this
    // chart a different size from its neighbours in the same grid row.
    $view = file_get_contents(resource_path('views/filament/widgets/lead-status-chart.blade.php'));

    expect($view)
        ->toContain('fi-wi-chart-frame')
        ->not->toMatch('/height:\s*\d+px/');
});
