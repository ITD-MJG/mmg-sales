<?php

namespace Tests\Feature;

use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defaults confidence to the status weight on create', function (string $status, int $expected) {
    $lead = Lead::factory()->create(['status' => $status, 'confidence_level' => null]);

    expect($lead->fresh()->confidence_level)->toBe($expected);
})->with([
    ['new', 10],
    ['prospect', 20],
    ['qualification', 30],
    ['quotation', 40],
    ['convince', 50],
    ['negotiate', 60],
    ['decision_maker', 70],
    ['closed_won', 100],
    ['closed_lost', 0],
    ['hold', 30],
]);

it('keeps an explicitly set confidence on create', function () {
    $lead = Lead::factory()->create(['status' => 'negotiate', 'confidence_level' => 45]);

    expect($lead->fresh()->confidence_level)->toBe(45);
});

it('moves confidence to the new status weight when the status changes', function () {
    $lead = Lead::factory()->create(['status' => 'new', 'confidence_level' => 10]);

    $lead->update(['status' => 'decision_maker']);

    expect($lead->fresh()->confidence_level)->toBe(70);
});

it('keeps an explicitly changed confidence when the status changes in the same save', function () {
    $lead = Lead::factory()->create(['status' => 'new', 'confidence_level' => 10]);

    $lead->update(['status' => 'decision_maker', 'confidence_level' => 55]);

    expect($lead->fresh()->confidence_level)->toBe(55);
});
