<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retires `converted` from the lead status enum.
 *
 * A lead is raw intake: it records whether somebody was reached and whether the
 * contact was judged not interested. Winning is not a lead concept — it belongs
 * to whatever closes the deal — so the status stops at contacted/disqualified.
 *
 * Existing `converted` rows become `contacted`: they did reach the contact, and
 * `converted_at` is kept so the date the deal closed is not lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leads')) {
            return;
        }

        // Remap first, then narrow: altering the enum while rows still hold the
        // doomed value truncates them to '' instead of failing cleanly.
        DB::table('leads')
            ->where('status', 'converted')
            ->update(['status' => 'contacted']);

        DB::statement('ALTER TABLE leads MODIFY status ENUM("new","contacted","disqualified") NOT NULL DEFAULT "new"');
    }

    public function down(): void
    {
        if (! Schema::hasTable('leads')) {
            return;
        }

        DB::statement('ALTER TABLE leads MODIFY status ENUM("new","contacted","converted","disqualified") NOT NULL DEFAULT "new"');

        // The original status cannot be recovered from `contacted`, so restore
        // it from the timestamp the conversion stamped.
        DB::table('leads')
            ->where('status', 'contacted')
            ->whereNotNull('converted_at')
            ->update(['status' => 'converted']);
    }
};
