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
