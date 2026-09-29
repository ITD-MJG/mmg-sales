<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The `items` table is legacy. No foreign key ever referenced it: the
 * `order_items.item_id` column was renamed from `product_id` but kept its
 * original foreign key to `products`, and `orders.item_id` was dropped.
 * `products` is the canonical catalog, so any surviving `items` rows are
 * carried across before the table is removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('items')) {
            return;
        }

        if (Schema::hasTable('products')) {
            $this->widenProductUnitPrice();
            $this->copyItemsIntoProducts();
        }

        Schema::drop('items');
    }

    public function down(): void
    {
        if (Schema::hasTable('items')) {
            return;
        }

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('internal_code')->unique();
            $table->string('principle_code')->nullable();
            $table->string('name');
            $table->unsignedBigInteger('principal_id');
            $table->text('description')->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('ecatalog_price', 12, 2)->nullable();
            $table->string('unit')->default('unit');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('principal_id')->references('id')->on('principals')->onDelete('cascade');
        });

        // The rows merged into `products` are not copied back. `products` is the
        // canonical catalog and the merge is a one-way consolidation.
    }

    /**
     * `items.unit_price` was decimal(15,2) while `products.unit_price` is
     * decimal(12,2), which cannot hold every legacy price.
     */
    private function widenProductUnitPrice(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE `products` MODIFY `unit_price` DECIMAL(15,2) NOT NULL');
    }

    private function copyItemsIntoProducts(): void
    {
        $existingNames = DB::table('products')->pluck('name')->all();
        $seen = array_fill_keys($existingNames, true);
        $now = now();

        DB::table('items')->orderBy('id')->chunk(200, function ($items) use (&$seen, $now): void {
            $rows = [];

            foreach ($items as $item) {
                if (isset($seen[$item->name])) {
                    continue;
                }

                $seen[$item->name] = true;

                $rows[] = [
                    'internal_code' => $item->internal_code,
                    'name' => $item->name,
                    'category' => $item->unit === 'Unit' ? 'medical_equipment' : 'consumables',
                    'description' => $item->description,
                    'unit_price' => $item->unit_price,
                    'ecatalog_price' => $item->ecatalog_price,
                    'unit_of_measure' => $item->unit,
                    'is_active' => $item->is_active,
                    'principal_id' => $item->principal_id,
                    'created_at' => $item->created_at ?? $now,
                    'updated_at' => $item->updated_at ?? $now,
                ];
            }

            if ($rows !== []) {
                DB::table('products')->insert($rows);
            }
        });
    }
};
