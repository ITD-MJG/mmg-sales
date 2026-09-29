<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `2026_07_23_094056` renamed `orders.sr_position_id` -> `sales` and
 * `Order::$casts` declares it `array`, but the column was never widened
 * from `bigint`. Writing an array therefore fails with
 * "Incorrect integer value: '[7]'", and the report joins that were
 * switched from JSON_CONTAINS to a plain equality join in `cf35331`
 * can never match.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->convert('json');
    }

    public function down(): void
    {
        $this->convert('bigint');
    }

    private function convert(string $target): void
    {
        if (! Schema::hasColumn('orders', 'sales')) {
            return;
        }

        // `2026_07_23_094056` renamed the column but left the foreign key
        // `orders_sr_position_id_foreign` attached to `positions`.
        $this->dropStalePositionForeignKey();

        if (DB::getDriverName() === 'sqlite') {
            // SQLite is dynamically typed, so no cast is required.
            return;
        }

        // Widen first so existing scalars can be read and re-encoded.
        DB::statement('ALTER TABLE `orders` MODIFY `sales` LONGTEXT NULL');

        DB::table('orders')->select('id', 'sales')->orderBy('id')->chunkById(200, function ($orders) use ($target): void {
            foreach ($orders as $order) {
                DB::table('orders')->where('id', $order->id)->update([
                    'sales' => $this->encode($order->sales, $target),
                ]);
            }
        });

        if ($target === 'json') {
            DB::statement('ALTER TABLE `orders` MODIFY `sales` JSON NULL');
        } else {
            DB::statement('ALTER TABLE `orders` MODIFY `sales` BIGINT UNSIGNED NULL');
        }

    }

    private function dropStalePositionForeignKey(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('orders', function (Blueprint $table) {
                try {
                    $table->dropForeign(['sales']);
                } catch (Throwable $e) {
                    // Column may not carry a foreign key.
                }
            });

            return;
        }

        $exists = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'orders')
            ->where('COLUMN_NAME', 'sales')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('CONSTRAINT_NAME');

        foreach ($exists as $constraint) {
            DB::statement("ALTER TABLE `orders` DROP FOREIGN KEY `{$constraint}`");
        }
    }

    /**
     * Legacy rows hold a bare user id; rows written after the rename may
     * already hold a JSON array or a comma-separated list.
     */
    private function encode(?string $value, string $target): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            $ids = is_array($decoded) ? $decoded : [$decoded];
        } elseif (str_contains($value, ',')) {
            $ids = array_map('trim', explode(',', $value));
        } else {
            $ids = [$value];
        }

        $ids = array_values(array_filter($ids, fn ($id) => $id !== null && $id !== ''));

        if ($target === 'bigint') {
            return $ids === [] ? null : (string) (int) $ids[0];
        }

        return $ids === [] ? null : json_encode(array_map('intval', $ids));
    }
};
