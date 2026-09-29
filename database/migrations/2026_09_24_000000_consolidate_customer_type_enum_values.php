<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The customer type values the application has used since the type set was
     * consolidated: forms, tables, imports, exports, factories and tests all
     * read and write these three.
     */
    private const CURRENT_TYPES = ['hospital_clinic', 'pt_cv', 'other'];

    /**
     * The values the column was originally created with.
     */
    private const LEGACY_TYPES = ['hospital', 'clinic', 'pharmacy', 'laboratory', 'distributor', 'other'];

    /**
     * Legacy values folded into the consolidated set.
     */
    private const FORWARD_MAP = [
        'hospital' => 'hospital_clinic',
        'clinic' => 'hospital_clinic',
        'pharmacy' => 'hospital_clinic',
        'laboratory' => 'other',
        'distributor' => 'other',
    ];

    /**
     * Reverse of FORWARD_MAP, best effort: the consolidation is lossy, so the
     * specific facility kind cannot be recovered.
     */
    private const REVERSE_MAP = [
        'hospital_clinic' => 'hospital',
        'pt_cv' => 'other',
    ];

    public function up(): void
    {
        $this->widenToUnion();

        foreach (self::FORWARD_MAP as $from => $to) {
            DB::table('customers')->where('type', $from)->update(['type' => $to]);
        }

        $this->applyTypes(self::CURRENT_TYPES);
    }

    public function down(): void
    {
        $this->widenToUnion();

        foreach (self::REVERSE_MAP as $from => $to) {
            DB::table('customers')->where('type', $from)->update(['type' => $to]);
        }

        $this->applyTypes(self::LEGACY_TYPES);
    }

    /**
     * Widen the column to accept both the old and new values.
     *
     * The data remap has to run while every value from either set is still
     * legal, otherwise MariaDB silently truncates the row to an empty string.
     * SQLite keeps the column as a plain string and enforces enum values with a
     * CHECK constraint, so widening is a no-op there.
     */
    private function widenToUnion(): void
    {
        $this->applyTypes(array_values(array_unique([...self::LEGACY_TYPES, ...self::CURRENT_TYPES])));
    }

    /**
     * @param  list<string>  $types
     */
    private function applyTypes(array $types): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('type', 50)->default('other')->change();
            });

            return;
        }

        $enum = implode(', ', array_map(fn (string $type) => "'{$type}'", $types));

        DB::statement("ALTER TABLE customers MODIFY COLUMN type ENUM({$enum}) NOT NULL DEFAULT 'other'");
    }
};
