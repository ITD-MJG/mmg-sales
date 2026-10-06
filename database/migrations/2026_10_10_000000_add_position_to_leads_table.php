<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The leads table was recreated in 2026_09_26_000002 without the `position`
 * column that 2026_01_07_034541 had added to the earlier incarnation, so the
 * kanban board has nowhere to store card order. Re-add it here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->flowforgePositionColumn('position');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
