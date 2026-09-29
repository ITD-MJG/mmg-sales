<?php

use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('has creator relationship', function () {
    $creator = User::factory()->create();
    $lead = Lead::factory()->create([
        'created_by' => $creator->id,
    ]);

    expect($lead->creator)->toBeInstanceOf(User::class);
    expect($lead->creator->id)->toBe($creator->id);
});

it('relates the opportunities it produced', function () {
    $creator = User::factory()->create();
    $lead = Lead::factory()->create([
        'created_by' => $creator->id,
    ]);

    $opportunity = Opportunity::factory()->create(['converted_from_lead_id' => $lead->id]);

    expect($lead->opportunities)->toHaveCount(1);
    expect($lead->opportunities->first()->id)->toBe($opportunity->id);
});

it('auto sets created_by when creating lead with auth', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $lead = Lead::factory()->create([
        'created_by' => null,
    ]);

    expect($lead->created_by)->toBe($user->id);
});

it('does not overwrite existing created_by', function () {
    $creator = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser);

    $lead = Lead::factory()->create([
        'created_by' => $creator->id,
    ]);

    expect($lead->created_by)->toBe($creator->id);
});
