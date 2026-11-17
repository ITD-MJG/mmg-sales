<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The band vocabulary confidence used before it became a percentage.
     *
     * @var array<string, int>
     */
    private const BAND_TO_PERCENT = [
        'low' => 10,
        'medium' => 30,
        'high' => 60,
        'very_high' => 80,
        'committed' => 95,
    ];

    public function up(): void
    {
        foreach (self::BAND_TO_PERCENT as $band => $percent) {
            DB::table('leads')
                ->where('confidence_level', $band)
                ->update(['confidence_level' => $percent]);
        }

        // Any remaining non-numeric value is not a percentage, so it becomes
        // unassessed rather than being silently coerced to an arbitrary number.
        DB::table('leads')
            ->whereNotNull('confidence_level')
            ->whereRaw('confidence_level NOT REGEXP ?', ['^[0-9]{1,3}$'])
            ->update(['confidence_level' => null]);

        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedTinyInteger('confidence_level')->nullable()->change();
        });

        // Widen to nullable before writing nulls: the old column rejects them.
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedTinyInteger('confidence_level')->nullable()->default(null)->change();
        });

        // Activities used 0 as their "not assessed" sentinel. Null carries the
        // same meaning without stealing a genuine 0%.
        DB::table('activities')->where('confidence_level', 0)->update(['confidence_level' => null]);
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->integer('confidence_level')->default(0)->change();
        });

        DB::table('activities')->whereNull('confidence_level')->update(['confidence_level' => 0]);

        Schema::table('leads', function (Blueprint $table) {
            $table->string('confidence_level')->nullable()->change();
        });

        foreach (self::BAND_TO_PERCENT as $band => $percent) {
            DB::table('leads')
                ->where('confidence_level', (string) $percent)
                ->update(['confidence_level' => $band]);
        }
    }
};
