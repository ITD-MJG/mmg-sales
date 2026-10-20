<?php

use App\Models\Activity;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The mirror migration has already run as part of the test suite's migrate:fresh,
 * so these tests exercise its `up()` directly against seeded opportunities. That
 * is the real risk surface: the mapping and the re-points.
 */
function runMirror(): void
{
    (require base_path('database/migrations/2026_10_02_000000_mirror_opportunities_back_to_leads.php'))->up();
}

beforeEach(function () {
    // Reset any mirrored leads between tests. Mirrored codes always start with
    // LEAD-, so matching the prefix catches every shape the tests create.
    DB::table('leads')->where('lead_code', 'like', 'LEAD-%')->delete();
});

it('copies a production-shaped LEAD- code verbatim', function () {
    // M1 renamed the column but kept the values, so a production opportunity is
    // coded with the original lead code. Copying it back is what makes this a
    // true rollback; prefixing it would invent a code the business never saw.
    $opportunity = Opportunity::factory()->create([
        'opportunity_code' => 'LEAD-202605-0001',
        'stage' => 'proposal',
        'title' => 'Production Shaped Deal',
        'customer_id' => Customer::factory(),
    ]);

    runMirror();

    $lead = Lead::where('lead_code', 'LEAD-202605-0001')->first();

    expect($lead)->not->toBeNull()
        ->and($lead->title)->toBe('Production Shaped Deal')
        ->and($lead->customer_id)->toBe($opportunity->customer_id);
});

it('mirrors an opportunity into a lead with the code prefix swapped', function () {
    $opportunity = Opportunity::factory()->create([
        'opportunity_code' => 'OPP-202601-0001',
        'stage' => 'proposal',
        'title' => 'Mirrored Deal',
        'customer_id' => Customer::factory(),
    ]);

    runMirror();

    $lead = Lead::where('lead_code', 'LEAD-202601-0001')->first();

    expect($lead)->not->toBeNull()
        ->and($lead->title)->toBe('Mirrored Deal')
        ->and($lead->customer_id)->toBe($opportunity->customer_id);
});

it('flattens stage into the four-value lead status enum', function () {
    $cases = [
        'new' => 'new',
        'contacted' => 'contacted',
        'qualified' => 'contacted',
        'proposal' => 'contacted',
        'negotiation' => 'contacted',
        'won' => 'converted',
        'lost' => 'disqualified',
    ];

    foreach ($cases as $stage => $expected) {
        $opportunity = Opportunity::factory()->create([
            'opportunity_code' => 'OPP-202601-'.str_pad((string) (array_search($stage, array_keys($cases)) + 1), 4, '0', STR_PAD_LEFT),
            'stage' => $stage,
            'customer_id' => Customer::factory(),
        ]);

        runMirror();

        $code = 'LEAD-'.substr((string) $opportunity->opportunity_code, 4);
        $lead = Lead::where('lead_code', $code)->first();
        expect($lead->status)->toBe($expected, "stage {$stage} should map to {$expected}");
    }
});

it('sets converted_at for a won opportunity and disqualified_at for a lost one', function () {
    $won = Opportunity::factory()->create([
        'opportunity_code' => 'OPP-202601-0001',
        'stage' => 'won',
        'customer_id' => Customer::factory(),
    ]);

    $lost = Opportunity::factory()->create([
        'opportunity_code' => 'OPP-202601-0002',
        'stage' => 'lost',
        'customer_id' => Customer::factory(),
    ]);

    runMirror();

    $wonLead = Lead::where('lead_code', 'LEAD-202601-0001')->first();
    $lostLead = Lead::where('lead_code', 'LEAD-202601-0002')->first();

    expect($wonLead->converted_at)->not->toBeNull()
        ->and($lostLead->disqualified_at)->not->toBeNull()
        ->and($lostLead->converted_at)->toBeNull();
});

it('moves the activity trail from the opportunity onto the mirrored lead', function () {
    $opportunity = Opportunity::factory()->create([
        'opportunity_code' => 'OPP-202601-0001',
        'customer_id' => Customer::factory(),
    ]);

    $activity = Activity::factory()->create([
        'opportunity_id' => $opportunity->id,
        'lead_id' => null,
    ]);

    runMirror();

    $lead = Lead::where('lead_code', 'LEAD-202601-0001')->first();
    $activity->refresh();

    // The Activity model forbids both columns at once, so this is a swap.
    expect($activity->lead_id)->toBe($lead->id)
        ->and($activity->opportunity_id)->toBeNull();
});

it('is idempotent — running twice does not duplicate the mirrored lead', function () {
    Opportunity::factory()->create([
        'opportunity_code' => 'OPP-202601-0001',
        'customer_id' => Customer::factory(),
    ]);

    runMirror();
    runMirror();

    expect(Lead::where('lead_code', 'LEAD-202601-0001')->count())->toBe(1);
});

it('never deletes or alters the opportunity it mirrored', function () {
    $opportunity = Opportunity::factory()->create([
        'opportunity_code' => 'OPP-202601-0001',
        'stage' => 'won',
        'estimated_value' => 12345678.00,
        'customer_id' => Customer::factory(),
    ]);

    runMirror();

    $fresh = Opportunity::find($opportunity->id);

    expect($fresh)->not->toBeNull()
        ->and($fresh->stage)->toBe('won')
        ->and((float) $fresh->estimated_value)->toBe(12345678.0);
});

