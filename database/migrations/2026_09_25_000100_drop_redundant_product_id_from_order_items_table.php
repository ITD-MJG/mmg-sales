<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `order_items` carries two columns pointing at `products`:
 *
 *   item_id    NOT NULL, FK `order_items_product_id_foreign`     -> products (restrict)
 *   product_id NULL,     FK `order_items_product_id_foreign_new` -> products (set null)
 *
 * `product_id` is redundant. `2026_02_24_081504` renamed the column
 * `product_id` -> `item_id` without renaming its foreign key, then
 * `2026_02_27_035619` added a second `product_id`. Every report joins on
 * `item_id`; only some widgets join on `product_id`, so those widgets
 * silently returned no rows for orders written through the UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_items', 'product_id')) {
            return;
        }

        // Rows inserted before the UI wrote item_id only.
        DB::table('order_items')
            ->whereNull('item_id')
            ->whereNotNull('product_id')
            ->update(['item_id' => DB::raw('product_id')]);

        if (DB::getDriverName() === 'sqlite') {
            // SQLite rebuilds the table on dropColumn and carries foreign keys
            // across, so the FK on the dropped column must go first.
            Schema::table('order_items', function (Blueprint $table) {
                try {
                    $table->dropForeign(['product_id']);
                } catch (Throwable $e) {
                    // Column may not carry a foreign key.
                }
            });

            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('product_id');
            });

            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign('order_items_product_id_foreign_new');
            $table->dropColumn('product_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_items', 'product_id')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('principal_id');
        });

        DB::table('order_items')->update(['product_id' => DB::raw('item_id')]);

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('order_items', function (Blueprint $table) {
                $table->foreign('product_id', 'order_items_product_id_foreign_new')
                    ->references('id')
                    ->on('products')
                    ->onDelete('set null');
            });
        }
    }
};
