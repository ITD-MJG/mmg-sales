<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Targets change to one row per rep per month: the manual `actual` the rep
     * reports, and the monthly target it is measured against. The annual figure
     * is the sum of a year's monthly targets, so the separate annual row and its
     * column go away.
     */
    public function up(): void
    {
        Schema::table('targets', function (Blueprint $table) {
            $table->decimal('actual', 15, 2)->nullable()->after('monthly_target');
        });

        Schema::table('targets', function (Blueprint $table) {
            $table->dropColumn('annual_target');
        });
    }

    public function down(): void
    {
        Schema::table('targets', function (Blueprint $table) {
            $table->decimal('annual_target', 15, 2)->nullable()->after('month');
        });

        Schema::table('targets', function (Blueprint $table) {
            $table->dropColumn('actual');
        });
    }
};
