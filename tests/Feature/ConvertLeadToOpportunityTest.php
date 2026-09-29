<?php

use App\Actions\ConvertLeadToOpportunity;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a qualified opportunity from a lead', function () {
    $lead = Lead::factory()->create(['customer_id' => Customer::factory()]);

    $opportunity = app(ConvertLeadToOpportunity::class)->handle($lead);

    expect($opportunity)->toBeInstanceOf(Opportunity::class)
        ->and($opportunity->stage)->toBe('qualified')
        ->and($opportunity->converted_from_lead_id)->toBe($lead->id)
        ->and($opportunity->title)->toBe($lead->title)
        ->and($opportunity->customer_id)->toBe($lead->customer_id);
});

it('marks the lead converted', function () {
    $lead = Lead::factory()->create(['customer_id' => Customer::factory()]);

    app(ConvertLeadToOpportunity::class)->handle($lead);
    $lead->refresh();

    expect($lead->status)->toBe('converted')
        ->and($lead->converted_at)->not->toBeNull();
});

it('moves the activity trail onto the opportunity', function () {
    // Pinned so the still-legacy ActivityObserver cannot write an out-of-enum
    // status ('lost'/'qualified') onto the thin lead: a fresh 'new' lead plus a
    // neutral outcome only ever transitions it to 'contacted'.
    $lead = Lead::factory()->create([
        'customer_id' => Customer::factory(),
        'status' => 'new',
    ]);
    Activity::factory()->forLead($lead)->create(['outcome' => 'Interested']);

    $opportunity = app(ConvertLeadToOpportunity::class)->handle($lead);

    expect(Activity::where('opportunity_id', $opportunity->id)->count())->toBe(1)
        ->and(Activity::where('lead_id', $lead->id)->count())->toBe(0);
});

it('refuses a lead with no customer and writes nothing', function () {
    $lead = Lead::factory()->create(['customer_id' => null]);
    $leadId = $lead->id;
    $originalStatus = $lead->status;

    expect(fn () => app(ConvertLeadToOpportunity::class)->handle($lead))
        ->toThrow(InvalidArgumentException::class);

    // The throw must land before any write: no opportunity for the lead, and
    // the lead is untouched (original status, converted_at still null).
    $lead->refresh();

    expect(Opportunity::where('converted_from_lead_id', $leadId)->count())->toBe(0)
        ->and($lead->status)->toBe($originalStatus)
        ->and($lead->status)->not->toBe('converted')
        ->and($lead->converted_at)->toBeNull();
});

it('is idempotent', function () {
    $lead = Lead::factory()->create(['customer_id' => Customer::factory()]);
    $action = app(ConvertLeadToOpportunity::class);

    $first = $action->handle($lead);
    $second = $action->handle($lead->fresh());

    expect($second->id)->toBe($first->id)
        ->and(Opportunity::where('converted_from_lead_id', $lead->id)->count())->toBe(1);
});

it('returns an opportunity created outside the action instead of creating a second one', function () {
    $lead = Lead::factory()->create(['customer_id' => Customer::factory()]);

    // The row a committed concurrent conversion leaves behind, created directly.
    $existing = Opportunity::factory()->create([
        'converted_from_lead_id' => $lead->id,
    ]);

    $returned = app(ConvertLeadToOpportunity::class)->handle($lead);

    expect($returned->id)->toBe($existing->id)
        ->and(Opportunity::where('converted_from_lead_id', $lead->id)->count())->toBe(1);
});

it('re-checks inside the transaction when the fast path missed, so a racing conversion cannot double-create', function () {
    // The plan's named risk: two clicks, or a manual convert racing the
    // observer's auto-promotion. The pre-transaction lookup is only a fast path;
    // the authoritative check runs inside the transaction against a locked lead
    // row. This test makes the fast path miss and lets a "winning" conversion
    // land before the locked re-check, exactly the window a real race occupies.
    $lead = Lead::factory()->create(['customer_id' => Customer::factory()]);
    $leadId = $lead->id;

    // RefreshDatabase holds every test in an outer transaction, so the
    // baseline is 1, not 0. The action's own DB::transaction() raises it by
    // one; that delta is what separates the fast path from the re-check.
    $baselineLevel = DB::connection()->transactionLevel();

    $fastPathLookups = 0;
    $lockedRecheckLookups = 0;
    $raceInjected = false;
    $winner = null;

    DB::connection()->beforeExecuting(function (string $query, array $bindings, Connection $connection) use (
        &$fastPathLookups,
        &$lockedRecheckLookups,
        &$raceInjected,
        &$winner,
        $leadId,
        $baselineLevel
    ): void {
        if (str_contains($query, 'select * from `opportunities` where `converted_from_lead_id`')) {
            if ($connection->transactionLevel() > $baselineLevel) {
                $lockedRecheckLookups++;
            } else {
                $fastPathLookups++;
            }

            return;
        }
        // Intercept the lead row lock. This caller has already missed the fast
        // path; inject the winner's committed opportunity before the lock is
        // taken, so only the in-transaction re-check can see it.
        if (! $raceInjected
            && $connection->transactionLevel() > $baselineLevel
            && str_contains($query, 'select * from `leads`')
            && str_contains($query, 'for update')) {
            $raceInjected = true;

            $winner = Opportunity::factory()->create([
                'converted_from_lead_id' => $leadId,
            ]);
        }
    });

    // A stale in-memory instance, as a caller that lost the race would hold.
    $stale = Lead::find($leadId);

    $returned = app(ConvertLeadToOpportunity::class)->handle($stale);

    expect($fastPathLookups)->toBe(1)
        ->and($raceInjected)->toBeTrue()
        ->and($lockedRecheckLookups)->toBe(1)
        ->and($winner)->not->toBeNull()
        ->and($returned->id)->toBe($winner->id)
        ->and(Opportunity::where('converted_from_lead_id', $leadId)->count())->toBe(1);
});
