<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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

it('preserves legacy rows and products across the rename', function () {
    $migration = 'database/migrations/2026_09_26_000001_rename_leads_to_opportunities_and_split.php';

    // Roll M1 back one step so the pre-rename shape (`leads` + `status`, `lead_product`) exists.
    Artisan::call('migrate:rollback', ['--path' => $migration, '--force' => true]);

    // A product to hang a lead_product row off.
    $productId = DB::table('products')->insertGetId([
        'name' => 'Legacy Test Product',
        'unit_price' => 100000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Two legacy leads: one `new`, one `contacted`.
    $newLeadId = DB::table('leads')->insertGetId([
        'lead_code' => 'LEAD-M1-NEW',
        'title' => 'Legacy New Lead',
        'customer_name' => 'Acme Legacy',
        'phone' => '080000000001',
        'status' => 'new',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $contactedLeadId = DB::table('leads')->insertGetId([
        'lead_code' => 'LEAD-M1-CONTACTED',
        'title' => 'Legacy Contacted Lead',
        'customer_name' => 'Beta Legacy',
        'phone' => '080000000002',
        'status' => 'contacted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $productRowId = DB::table('lead_product')->insertGetId([
        'lead_id' => $newLeadId,
        'product_id' => $productId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Run M1 forward.
    Artisan::call('migrate', ['--path' => $migration, '--force' => true]);

    // Both leads survive with their stage values intact and unchanged ids.
    $newRow = DB::table('opportunities')->where('id', $newLeadId)->first();
    $contactedRow = DB::table('opportunities')->where('id', $contactedLeadId)->first();

    expect($newRow)->not->toBeNull()
        ->and($newRow->stage)->toBe('new');
    expect($contactedRow)->not->toBeNull()
        ->and($contactedRow->stage)->toBe('contacted');

    // The lead_product row survives and still points at the renamed opportunity.
    $productRow = DB::table('opportunity_product')->where('id', $productRowId)->first();

    expect($productRow)->not->toBeNull()
        ->and($productRow->opportunity_id)->toBe($newLeadId)
        ->and($productRow->product_id)->toBe($productId);

    // Leave the testing DB clean for other tests (schema stays migrated).
    DB::table('opportunity_product')->where('id', $productRowId)->delete();
    DB::table('opportunities')->whereIn('id', [$newLeadId, $contactedLeadId])->delete();
    DB::table('products')->where('id', $productId)->delete();
});
