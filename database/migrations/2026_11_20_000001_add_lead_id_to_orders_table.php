<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders may now record the lead they originated from.
 *
 * `lead_id` is an origin pointer, not a stage pointer: it says which intake the
 * revenue came from and is never cleared or re-pointed when that lead is later
 * converted. An opportunity created from a lead already carries
 * `converted_from_lead_id`, so the deal an order belongs to stays reachable via
 * `$order->lead->opportunity` without rewriting history.
 *
 * `opportunity_id` is left untouched. The two columns are mutually exclusive when
 * set, but both may be null: orders predate the lead link and legitimately exist
 * without either.
 *
 * Nulls rather than cascades on delete — losing a lead must never delete revenue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('lead_id')
                ->nullable()
                ->after('end_customer_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lead_id');
        });
    }
};
