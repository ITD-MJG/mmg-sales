<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'order_source')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('order_source')->nullable()->after('order_number');
            });
        }
        if (! Schema::hasColumn('orders', 'po_number')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('po_number')->nullable()->after('order_source');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'po_number') || Schema::hasColumn('orders', 'order_source')) {
            Schema::table('orders', function (Blueprint $table): void {
                $columns = array_values(array_filter(
                    ['order_source', 'po_number'],
                    fn (string $column): bool => Schema::hasColumn('orders', $column),
                ));

                $table->dropColumn($columns);
            });
        }
    }
};
