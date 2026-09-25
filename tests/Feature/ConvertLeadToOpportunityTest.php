<?php

use App\Actions\ConvertLeadToOpportunity;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
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

it('refuses a lead with no customer', function () {
    $lead = Lead::factory()->create(['customer_id' => null]);

    expect(fn () => app(ConvertLeadToOpportunity::class)->handle($lead))
        ->toThrow(InvalidArgumentException::class);
});

it('is idempotent', function () {
    $lead = Lead::factory()->create(['customer_id' => Customer::factory()]);
    $action = app(ConvertLeadToOpportunity::class);

    $first = $action->handle($lead);
    $second = $action->handle($lead->fresh());

    expect($second->id)->toBe($first->id)
        ->and(Opportunity::where('converted_from_lead_id', $lead->id)->count())->toBe(1);
});
