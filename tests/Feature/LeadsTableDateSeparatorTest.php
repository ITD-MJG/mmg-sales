<?php

namespace Tests\Feature;

use App\Filament\Resources\Leads\Pages\ListLeads;
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

function leadCreatedAt(string $createdAt): Lead
{
    $lead = Lead::factory()->create(['created_by' => auth()->id()]);
    Lead::whereKey($lead->id)->update(['created_at' => $createdAt]);

    return $lead->refresh();
}

it('separates leads into one group per creation day', function () {
    $firstDay = now()->subDays(2)->startOfDay()->addHours(9);
    $secondDay = now()->subDay()->startOfDay()->addHours(14);

    leadCreatedAt($firstDay->toDateTimeString());
    leadCreatedAt($firstDay->copy()->addHours(3)->toDateTimeString());
    leadCreatedAt($secondDay->toDateTimeString());

    $html = Livewire::test(ListLeads::class)
        ->assertSuccessful()
        ->html();

    // One separator per day, not one per record.
    expect(substr_count($html, 'fi-ta-group-heading'))->toBe(2);
});

it('orders the lead separators by creation day', function () {
    $firstDay = now()->subDays(2)->startOfDay()->addHours(9);
    $secondDay = now()->subDay()->startOfDay()->addHours(14);

    $older = leadCreatedAt($firstDay->toDateTimeString());
    $newer = leadCreatedAt($secondDay->toDateTimeString());

    $html = Livewire::test(ListLeads::class)
        ->sortTable('created_at', 'asc')
        ->assertCanSeeTableRecords([$older, $newer], inOrder: true)
        ->html();

    $firstHeader = strtoupper($firstDay->translatedFormat('d M Y'));
    $secondHeader = strtoupper($secondDay->translatedFormat('d M Y'));

    expect(strpos($html, $firstHeader))->toBeLessThan(strpos($html, $secondHeader));
});
