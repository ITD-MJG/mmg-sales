<?php

use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Widgets\UpcomingLeadsWidget;
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
    $user = User::factory()->create();
    $user->assignRole('Super Admin');
    actingAs($user);
});

it('displays leads nearing completion sorted by date', function () {
    $p1 = Lead::factory()->create([
        'title' => 'Lead 1',
        'expected_closing_date' => now()->addDays(5),
    ]);

    $p2 = Lead::factory()->create([
        'title' => 'Lead 2',
        'expected_closing_date' => now()->addDays(2),
    ]);

    $p3 = Lead::factory()->create([
        'title' => 'Lead 3',
        'expected_closing_date' => now()->subDays(1), // Should be excluded by >= now()
    ]);

    livewire(UpcomingLeadsWidget::class)
        ->assertCanSeeTableRecords([$p2, $p1])
        ->assertCanNotSeeTableRecords([$p3])
        ->assertTableActionExists('view');
});

it('can navigate to view lead page from record url', function () {
    $lead = Lead::factory()->create([
        'expected_closing_date' => now()->addDays(1),
    ]);

    livewire(UpcomingLeadsWidget::class)
        ->assertTableActionHasUrl('view', LeadResource::getUrl('view', ['record' => $lead]), $lead);
});

it('keeps closed leads out of the leads nearing completion', function () {
    // A won deal is already complete, so it must not hold one of the five slots.
    $won = Lead::factory()->create([
        'title' => 'Won Lead',
        'status' => 'closed_won',
        'expected_closing_date' => now()->addDays(3),
    ]);

    $open = Lead::factory()->create([
        'title' => 'Open Lead',
        'status' => 'negotiate',
        'expected_closing_date' => now()->addDays(3),
    ]);

    livewire(UpcomingLeadsWidget::class)
        ->assertCanSeeTableRecords([$open])
        ->assertCanNotSeeTableRecords([$won]);
});

it('keeps a lead closing beyond the next month out of the widget', function () {
    $farOff = Lead::factory()->create([
        'title' => 'Far Off Lead',
        'status' => 'negotiate',
        'expected_closing_date' => now()->addDays(90),
    ]);

    livewire(UpcomingLeadsWidget::class)
        ->assertCanNotSeeTableRecords([$farOff]);
});
