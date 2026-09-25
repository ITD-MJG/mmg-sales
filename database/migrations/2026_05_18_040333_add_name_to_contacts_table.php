<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('name')->after('last_name');
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::table('contacts')->update([
                'name' => DB::raw("TRIM(COALESCE(first_name, '')) || ' ' || TRIM(COALESCE(last_name, ''))"),
            ]);
        } else {
            DB::table('contacts')->update([
                'name' => DB::raw('CONCAT(TRIM(first_name), " ", TRIM(last_name))'),
            ]);
        }

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('first_name')->after('contact_code');
            $table->string('last_name')->after('first_name');
        });

        if (DB::getDriverName() === 'sqlite') {
            // SQLite has no SUBSTRING_INDEX: first token and everything after it.
            DB::table('contacts')->update([
                'first_name' => DB::raw("CASE WHEN instr(name, ' ') > 0 THEN substr(name, 1, instr(name, ' ') - 1) ELSE name END"),
                'last_name' => DB::raw("CASE WHEN instr(name, ' ') > 0 THEN substr(name, instr(name, ' ') + 1) ELSE '' END"),
            ]);
        } else {
            DB::table('contacts')->update([
                'first_name' => DB::raw("SUBSTRING_INDEX(name, ' ', 1)"),
                'last_name' => DB::raw("SUBSTRING_INDEX(name, ' ', -1)"),
            ]);
        }

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
