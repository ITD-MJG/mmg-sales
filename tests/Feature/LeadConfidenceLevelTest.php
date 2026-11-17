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

it('buckets confidence into ordered percentage ranges', function () {
    expect(array_keys(Lead::CONFIDENCE_RANGES))->toBe(['0-25', '26-50', '51-75', '76-100'])
        ->and(Lead::confidenceFilterOptions())->toBe([
            '0-25' => 'Low (0-25%)',
            '26-50' => 'Medium (26-50%)',
            '51-75' => 'High (51-75%)',
            '76-100' => 'Very High (76-100%)',
            'none' => 'Not assessed',
        ])
        ->and(Lead::confidenceRange('76-100'))->toBe([76, 100])
        ->and(Lead::confidenceRange('none'))->toBeNull()
        ->and(Lead::confidenceRange(null))->toBeNull();
});

it('formats a percentage for display', function () {
    expect(Lead::confidenceLabel(80))->toBe('80%')
        ->and(Lead::confidenceLabel(0))->toBe('0%')
        ->and(Lead::confidenceLabel(null))->toBe('Not assessed');
});

it('treats an unassessed lead as unknown rather than worthless', function () {
    $unassessed = Lead::factory()->create();
    $unassessed->update(['confidence_level' => null]);

    expect($unassessed->fresh()->isConfidenceAssessed())->toBeFalse()
        ->and(Lead::factory()->create(['confidence_level' => 0])->isConfidenceAssessed())->toBeTrue();
});

it('leaves estimated revenue untouched by confidence', function () {
    // Confidence is a judgement, not a multiplier: pipeline value stays the
    // raw estimated revenue until an order turns it into actual revenue.
    $lead = Lead::factory()->create([
        'confidence_level' => 10,
        'estimated_revenue' => 100_000_000,
    ]);

    expect((float) $lead->estimated_revenue)->toBe(100_000_000.0);
});

it('captures confidence on the lead form', function () {
    livewire(CreateLead::class)
        ->assertFormFieldExists('confidence_level');
});

it('saves a chosen confidence percentage on a new lead', function () {
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
            'confidence_level' => 80,
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
        ->toBe(80);
});

it('shows confidence as a column and a filter on the lead list', function () {
    $assessed = Lead::factory()->create(['confidence_level' => 85, 'created_by' => $this->user->id]);
    $unassessed = Lead::factory()->create(['created_by' => $this->user->id]);
    $unassessed->update(['confidence_level' => null]);

    livewire(ListLeads::class)
        ->assertTableColumnExists('confidence_level')
        ->assertTableFilterExists('confidence_level')
        ->assertCanSeeTableRecords([$assessed, $unassessed])
        ->filterTable('confidence_level', 'none')
        ->assertCanSeeTableRecords([$unassessed])
        ->assertCanNotSeeTableRecords([$assessed])
        ->filterTable('confidence_level', '76-100')
        ->assertCanSeeTableRecords([$assessed])
        ->assertCanNotSeeTableRecords([$unassessed]);
});

it('prefills the activity confidence from the selected lead', function () {
    $lead = Lead::factory()->create(['confidence_level' => 60]);

    livewire(CreateActivity::class)
        ->fillForm(['lead_id' => $lead->id])
        ->assertSchemaStateSet(['confidence_level' => 60]);
});

it('writes confidence from a new activity back to the lead', function () {
    $lead = Lead::factory()->create(['confidence_level' => 10]);

    Activity::factory()->forLead($lead)->create([
        'performed_at' => now(),
        'confidence_level' => 95,
    ]);

    expect($lead->fresh()->confidence_level)->toBe(95);
});

it('does not let a backdated activity overwrite newer confidence', function () {
    $lead = Lead::factory()->create(['confidence_level' => 10]);

    Activity::factory()->forLead($lead)->create([
        'performed_at' => now(),
        'confidence_level' => 80,
    ]);

    expect($lead->fresh()->confidence_level)->toBe(80);

    Activity::factory()->forLead($lead)->create([
        'performed_at' => now()->subDays(10),
        'confidence_level' => 10,
    ]);

    expect($lead->fresh()->confidence_level)->toBe(80);
});

it('ignores an activity with no confidence when rolling up', function () {
    $lead = Lead::factory()->create(['confidence_level' => 30]);

    Activity::factory()->forLead($lead)->create([
        'performed_at' => now(),
        'confidence_level' => null,
    ]);

    expect($lead->fresh()->confidence_level)->toBe(30);
});
