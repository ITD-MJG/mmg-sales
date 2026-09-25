<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('renames leads to opportunities with renamed columns', function () {
    expect(Schema::hasTable('opportunities'))->toBeTrue()
        ->and(Schema::hasTable('leads'))->toBeFalse()
        ->and(Schema::hasColumn('opportunities', 'opportunity_code'))->toBeTrue()
        ->and(Schema::hasColumn('opportunities', 'stage'))->toBeTrue()
        ->and(Schema::hasColumn('opportunities', 'lead_code'))->toBeFalse()
        ->and(Schema::hasColumn('opportunities', 'status'))->toBeFalse();
});

it('renames the pivot tables and their foreign keys', function () {
    expect(Schema::hasTable('opportunity_collaborators'))->toBeTrue()
        ->and(Schema::hasTable('opportunity_product'))->toBeTrue()
        ->and(Schema::hasColumn('opportunity_collaborators', 'opportunity_id'))->toBeTrue()
        ->and(Schema::hasColumn('opportunity_product', 'opportunity_id'))->toBeTrue()
        ->and(Schema::hasTable('lead_collaborators'))->toBeFalse()
        ->and(Schema::hasTable('lead_product'))->toBeFalse();
});

it('drops the milestone tables', function () {
    expect(Schema::hasTable('lead_milestone'))->toBeFalse()
        ->and(Schema::hasTable('milestones'))->toBeFalse();
});

it('moves orders and activities onto opportunity_id', function () {
    expect(Schema::hasColumn('orders', 'opportunity_id'))->toBeTrue()
        ->and(Schema::hasColumn('orders', 'lead_id'))->toBeFalse()
        ->and(Schema::hasColumn('activities', 'opportunity_id'))->toBeTrue()
        ->and(Schema::hasColumn('activities', 'lead_id'))->toBeFalse();
});

it('preserves all seven legacy stage values in the enum', function () {
    $column = collect(DB::select('SHOW COLUMNS FROM opportunities'))
        ->firstWhere('Field', 'stage');

    foreach (['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost'] as $stage) {
        expect($column->Type)->toContain($stage);
    }
});
