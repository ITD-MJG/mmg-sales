<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\RevenueByTerritoryChart;
use App\Filament\Widgets\TopVisitedLeadsChart;
use App\Models\Activity;
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
    $component = livewire(TopVisitedLeadsChart::class)->assertOk();

    return (function (): array {
        return $this->getData();
    })->call($component->instance());
}

it('uses a three column dashboard layout', function () {
    expect((new Dashboard)->getColumns())->toBe(3);
});

it('does not register the revenue by territory chart on the dashboard', function () {
    expect(array_map(fn ($widget) => is_string($widget) ? $widget : $widget->widget, (new Dashboard)->getWidgets()))
        ->not->toContain(RevenueByTerritoryChart::class);
});

it('registers the top visits by lead chart on the dashboard', function () {
    expect((new Dashboard)->getWidgets())->toContain(TopVisitedLeadsChart::class);
});

it('renders the top leads by activity chart', function () {
    livewire(TopVisitedLeadsChart::class)
        ->assertSee('Top Leads by Activity')
        ->assertOk();
});

it('ranks leads by activity count, highest first, and excludes activities without a lead', function () {
    $busyLead = Lead::factory()->create(['title' => 'Busy Lead']);
    $quietLead = Lead::factory()->create(['title' => 'Quiet Lead']);

    Activity::factory()->count(3)->create([
        'lead_id' => $busyLead->id,
        'user_id' => $this->user->id,
        'performed_at' => now(),
    ]);

    Activity::factory()->create([
        'lead_id' => $quietLead->id,
        'user_id' => $this->user->id,
        'performed_at' => now(),
    ]);

    Activity::factory()->create([
        'lead_id' => null,
        'user_id' => $this->user->id,
        'performed_at' => now(),
    ]);

    $data = chartData();

    // Highest first, and no ->reverse(): index 0 renders at the top of a
    // horizontal bar chart.
    expect($data['labels'])->toBe([
        'Busy Lead ('.$busyLead->lead_code.')',
        'Quiet Lead ('.$quietLead->lead_code.')',
    ])->and($data['datasets'][0]['data'])->toBe([3, 1]);
});

it('labels leads uniquely so same-titled leads stay distinguishable', function () {
    $first = Lead::factory()->create(['title' => 'STI']);
    $second = Lead::factory()->create(['title' => 'STI']);

    foreach ([$first, $second] as $lead) {
        Activity::factory()->create([
            'lead_id' => $lead->id,
            'user_id' => $this->user->id,
            'performed_at' => now(),
        ]);
    }

    $labels = chartData()['labels'];

    expect($labels)->toHaveCount(2)
        ->and(array_unique($labels))->toHaveCount(2)
        ->and($labels[0])->toContain($first->lead_code)
        ->and($labels[1])->toContain($second->lead_code);
});

it('limits the chart to the ten most active leads', function () {
    Lead::factory()->count(12)->create()->each(function (Lead $lead): void {
        Activity::factory()->create([
            'lead_id' => $lead->id,
            'user_id' => $this->user->id,
            'performed_at' => now(),
        ]);
    });

    expect(chartData()['labels'])->toHaveCount(10);
});

it('scopes the chart to the activities the user may see', function () {
    $otherUser = User::factory()->create();
    $otherUser->assignRole('Sales Staff');

    $visible = Lead::factory()->create(['title' => 'Visible Lead']);
    $hidden = Lead::factory()->create(['title' => 'Hidden Lead']);

    Activity::factory()->create([
        'lead_id' => $visible->id,
        'user_id' => $this->user->id,
        'performed_at' => now(),
    ]);

    Activity::factory()->count(5)->create([
        'lead_id' => $hidden->id,
        'user_id' => $otherUser->id,
        'performed_at' => now(),
    ]);

    // The Super Admin in beforeEach sees everything, so assert the scope
    // from the other user's side: their own activity is all they get.
    actingAs($otherUser);

    $labels = chartData()['labels'];

    expect($labels)->toHaveCount(1)
        ->and($labels[0])->toContain('Hidden Lead');
});
