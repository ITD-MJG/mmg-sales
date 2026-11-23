<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_statuses', function (Blueprint $table) {
            $table->date('effective_date')->nullable()->after('amount');
        });

        // Rows written before the column existed: the day the payment was
        // recorded is the only evidence of when it took effect.
        DB::table('payment_statuses')
            ->whereNotNull('created_at')
            ->update(['effective_date' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('payment_statuses', function (Blueprint $table) {
            $table->dropColumn('effective_date');
        });
    }
};
