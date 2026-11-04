<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Nullable on purpose: an unassessed lead must stay distinguishable
            // from one a rep deliberately rated. A default of 'medium' would
            // silently inflate every forecast with leads nobody has judged.
            $table->string('confidence_level')->nullable()->after('priority');
            $table->index('confidence_level');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['confidence_level']);
            $table->dropColumn('confidence_level');
        });
    }
};
