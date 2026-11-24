<?php

namespace Tests\Feature;

use App\Filament\Widgets\LeadsAtRiskWidget;
use App\Filament\Widgets\StaleLeadsWidget;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));
    seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create(['name' => 'Rep']);
    $this->user->assignRole('Super Admin');
    actingAs($this->user);
});

afterEach(function () {
    $this->travelBack();
});

it('lists an open lead whose closing date has passed', function () {
    Lead::factory()->create([
        'created_by' => $this->user->id,
        'title' => 'Overdue Hospital Deal',
        'status' => 'negotiate',
        'expected_closing_date' => '2026-06-01',
    ]);

    Livewire::test(LeadsAtRiskWidget::class)
        ->assertOk()
        ->assertSee('Overdue Hospital Deal');
});

it('keeps a lead closing beyond the next thirty days out of the risk list', function () {
    Lead::factory()->create([
        'created_by' => $this->user->id,
        'title' => 'Comfortable Deal',
        'status' => 'negotiate',
        'expected_closing_date' => '2026-09-30',
    ]);

    Livewire::test(LeadsAtRiskWidget::class)
        ->assertOk()
        ->assertDontSee('Comfortable Deal');
});

it('keeps closed leads out of the risk list', function () {
    Lead::factory()->create([
        'created_by' => $this->user->id,
        'title' => 'Already Won Deal',
        'status' => 'closed_won',
        'expected_closing_date' => '2026-06-01',
    ]);

    Livewire::test(LeadsAtRiskWidget::class)
        ->assertOk()
        ->assertDontSee('Already Won Deal');
});

it('orders the risk list by closing date so the most overdue deal comes first', function () {
    Lead::factory()->create([
        'created_by' => $this->user->id,
        'title' => 'Second Most Urgent',
        'status' => 'quotation',
        'expected_closing_date' => '2026-06-05',
    ]);
    Lead::factory()->create([
        'created_by' => $this->user->id,
        'title' => 'Most Urgent Deal',
        'status' => 'quotation',
        'expected_closing_date' => '2026-05-01',
    ]);

    Livewire::test(LeadsAtRiskWidget::class)
        ->assertOk()
        ->assertSeeInOrder(['Most Urgent Deal', 'Second Most Urgent']);
});

it('lists a lead that was never contacted before one that simply went quiet', function () {
    Lead::factory()->create([
        'created_by' => $this->user->id,
        'title' => 'Never Called Deal',
        'status' => 'prospect',
        'last_contacted_at' => null,
    ]);
    Lead::factory()->create([
        'created_by' => $this->user->id,
        'title' => 'Quiet For A Month Deal',
        'status' => 'prospect',
        'last_contacted_at' => '2026-05-01',
    ]);

    Livewire::test(StaleLeadsWidget::class)
        ->assertOk()
        ->assertSee('Never contacted')
        ->assertSeeInOrder(['Never Called Deal', 'Quiet For A Month Deal']);
});

it('keeps a lead contacted within the fortnight out of the quiet list', function () {
    Lead::factory()->create([
        'created_by' => $this->user->id,
        'title' => 'Recently Called Deal',
        'status' => 'prospect',
        'last_contacted_at' => '2026-06-10',
    ]);

    Livewire::test(StaleLeadsWidget::class)
        ->assertOk()
        ->assertDontSee('Recently Called Deal');
});

it('keeps closed leads out of the quiet list', function () {
    Lead::factory()->create([
        'created_by' => $this->user->id,
        'title' => 'Closed And Quiet Deal',
        'status' => 'closed_lost',
        'last_contacted_at' => null,
    ]);

    Livewire::test(StaleLeadsWidget::class)
        ->assertOk()
        ->assertDontSee('Closed And Quiet Deal');
});

it('spans the whole dashboard row, since it is the only list on the homepage', function () {
    expect(app(LeadsAtRiskWidget::class)->getColumnSpan())->toBe('full');
});
