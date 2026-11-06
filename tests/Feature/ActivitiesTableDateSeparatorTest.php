<?php

namespace Tests\Feature;

use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('Super Admin');
    Livewire::actingAs($this->user);
});

it('separates activities into one group per day', function () {
    $lead = Lead::factory()->create(['created_by' => $this->user->id]);
    $firstDay = now()->subDays(2)->startOfDay()->addHours(9);
    $secondDay = now()->subDay()->startOfDay()->addHours(14);

    $older = Activity::factory()->forLead($lead)->create([
        'user_id' => $this->user->id,
        'performed_at' => $firstDay,
    ]);
    $sameDay = Activity::factory()->forLead($lead)->create([
        'user_id' => $this->user->id,
        'performed_at' => $firstDay->copy()->addHours(3),
    ]);
    $newer = Activity::factory()->forLead($lead)->create([
        'user_id' => $this->user->id,
        'performed_at' => $secondDay,
    ]);

    $html = Livewire::test(ListActivities::class)
        ->assertCanSeeTableRecords([$newer, $sameDay, $older], inOrder: true)
        ->html();

    $firstHeader = strtoupper($firstDay->translatedFormat('d M Y'));
    $secondHeader = strtoupper($secondDay->translatedFormat('d M Y'));

    // One separator per day, not one per record.
    expect(substr_count($html, 'fi-ta-group-heading'))->toBe(2);

    // The newer day sorts first under the default descending order.
    expect(strpos($html, $secondHeader))->toBeLessThan(strpos($html, $firstHeader));
});

it('keeps the date separator aligned with the date sort direction', function () {
    $lead = Lead::factory()->create(['created_by' => $this->user->id]);
    $firstDay = now()->subDays(2)->startOfDay()->addHours(9);
    $secondDay = now()->subDay()->startOfDay()->addHours(14);

    Activity::factory()->forLead($lead)->create([
        'user_id' => $this->user->id,
        'performed_at' => $firstDay,
    ]);
    Activity::factory()->forLead($lead)->create([
        'user_id' => $this->user->id,
        'performed_at' => $secondDay,
    ]);

    $html = Livewire::test(ListActivities::class)
        ->sortTable('performed_at', 'asc')
        ->html();

    $firstHeader = strtoupper($firstDay->translatedFormat('d M Y'));
    $secondHeader = strtoupper($secondDay->translatedFormat('d M Y'));

    expect(strpos($html, $firstHeader))->toBeLessThan(strpos($html, $secondHeader));
});
