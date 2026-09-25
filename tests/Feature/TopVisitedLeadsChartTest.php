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

it('renders the top visits by lead chart', function () {
    livewire(TopVisitedLeadsChart::class)
        ->assertSee('Top Visits by Lead')
        ->assertOk();
});

it('ranks leads by visit count and excludes activities without a lead', function () {
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

    $component = livewire(TopVisitedLeadsChart::class)->assertOk();

    $data = (function () {
        return $this->getData();
    })->call($component->instance());

    expect($data['labels'])->toBe(['Busy Lead', 'Quiet Lead'])
        ->and($data['datasets'][0]['data'])->toBe([3, 1]);
});
