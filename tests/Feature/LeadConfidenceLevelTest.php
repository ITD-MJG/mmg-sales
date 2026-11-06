<?php

use App\Filament\Resources\Activities\Pages\CreateActivity;
use App\Filament\Resources\Leads\Pages\CreateLead;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Principal;
use App\Models\Product;
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

it('maps every confidence band to a published weight', function () {
    expect(Lead::CONFIDENCE_WEIGHTS)->toBe([
        'low' => 10,
        'medium' => 30,
        'high' => 60,
        'very_high' => 80,
        'committed' => 95,
    ])
        ->and(array_keys(Lead::CONFIDENCE_LABELS))->toBe(array_keys(Lead::CONFIDENCE_WEIGHTS));
});

it('exposes the weight of a banded lead', function () {
    $lead = Lead::factory()->create(['confidence_level' => 'high']);

    expect($lead->confidence_weight)->toBe(60);
});

it('treats an unassessed lead as unknown rather than worthless', function () {
    $unassessed = Lead::factory()->create(['confidence_level' => null]);

    expect($unassessed->confidence_weight)->toBeNull()
        ->and($unassessed->isConfidenceAssessed())->toBeFalse();
});

it('leaves estimated revenue untouched by confidence', function () {
    // Confidence is a judgement, not a multiplier: pipeline value stays the
    // raw estimated revenue until an order turns it into actual revenue.
    $lead = Lead::factory()->create([
        'confidence_level' => 'low',
        'estimated_revenue' => 100_000_000,
    ]);

    expect((float) $lead->estimated_revenue)->toBe(100_000_000.0);
});

it('round-trips a band from its weight', function () {
    expect(Lead::confidenceBandForWeight(80))->toBe('very_high')
        ->and(Lead::confidenceBandForWeight(0))->toBeNull()
        ->and(Lead::confidenceBandForWeight(null))->toBeNull()
        ->and(Lead::confidenceBandForWeight(73))->toBeNull();
});

it('captures confidence on the lead form', function () {
    livewire(CreateLead::class)
        ->assertFormFieldExists('confidence_level');
});

it('saves a chosen confidence band on a new lead', function () {
    $customer = Customer::factory()->create(['status' => 'active']);
    $principal = Principal::factory()->create(['is_active' => true]);
    $product = Product::factory()->create([
        'principal_id' => $principal->id,
        'is_active' => true,
        'unit_price' => 100_000_000,
    ]);

    livewire(CreateLead::class)
        ->fillForm([
            'title' => 'Radiology equipment refresh',
            'customer_id' => $customer->id,
            'phone' => '081234567890',
            'source' => 'referral',
            'priority' => 'high',
            'status' => 'new',
            'confidence_level' => 'very_high',
            'estimated_revenue' => 100_000_000,
            'expected_closing_date' => now()->addMonth()->toDateString(),
            'leadProductItems' => [[
                'principal_id' => $principal->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 100_000_000,
            ]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Lead::where('title', 'Radiology equipment refresh')->sole()->confidence_level)
        ->toBe('very_high');
});

it('shows confidence as a column and a filter on the lead list', function () {
    $banded = Lead::factory()->create(['confidence_level' => 'high', 'created_by' => $this->user->id]);
    $unassessed = Lead::factory()->create(['confidence_level' => null, 'created_by' => $this->user->id]);

    livewire(ListLeads::class)
        ->assertTableColumnExists('confidence_level')
        ->assertTableFilterExists('confidence_level')
        ->assertCanSeeTableRecords([$banded, $unassessed])
        ->filterTable('confidence_level', 'none')
        ->assertCanSeeTableRecords([$unassessed])
        ->assertCanNotSeeTableRecords([$banded]);
});

it('prefills the activity confidence from the selected lead', function () {
    $lead = Lead::factory()->create(['confidence_level' => 'high']);

    livewire(CreateActivity::class)
        ->fillForm(['lead_id' => $lead->id])
        ->assertSchemaStateSet(['confidence_level' => 60]);
});

it('writes confidence from a new activity back to the lead', function () {
    $lead = Lead::factory()->create(['confidence_level' => 'low']);

    Activity::factory()->forLead($lead)->create([
        'performed_at' => now(),
        'confidence_level' => 95,
    ]);

    expect($lead->fresh()->confidence_level)->toBe('committed');
});

it('does not let a backdated activity overwrite newer confidence', function () {
    $lead = Lead::factory()->create(['confidence_level' => 'low']);

    Activity::factory()->forLead($lead)->create([
        'performed_at' => now(),
        'confidence_level' => 80,
    ]);

    expect($lead->fresh()->confidence_level)->toBe('very_high');

    Activity::factory()->forLead($lead)->create([
        'performed_at' => now()->subDays(10),
        'confidence_level' => 10,
    ]);

    expect($lead->fresh()->confidence_level)->toBe('very_high');
});

it('ignores an activity with no confidence when rolling up', function () {
    $lead = Lead::factory()->create(['confidence_level' => 'medium']);

    Activity::factory()->forLead($lead)->create([
        'performed_at' => now(),
        'confidence_level' => 0,
    ]);

    expect($lead->fresh()->confidence_level)->toBe('medium');
});
