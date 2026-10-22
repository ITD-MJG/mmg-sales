<?php

use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('can save and retrieve estimated revenue', function () {
    $lead = Lead::factory()->create(['estimated_revenue' => 150000000.50]);

    expect($lead->estimated_revenue)->toBe('150000000.50');
});

it('stores the expected closing date on a lead', function () {
    $lead = Lead::factory()->create([
        'expected_closing_date' => '2026-12-31',
    ]);

    expect($lead->fresh()->expected_closing_date->format('Y-m-d'))->toBe('2026-12-31')
        ->and(Schema::hasColumn('leads', 'expected_closing_date'))->toBeTrue();
});
