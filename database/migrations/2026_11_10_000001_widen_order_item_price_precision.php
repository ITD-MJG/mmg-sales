<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A PO's Harga is a pre-VAT figure derived from a VAT-inclusive price, e.g.
 * 1.500.000 / 1,11 = 1.351.351,3514. The sheet prints it as 1.351.351 but
 * multiplies the full value, so nine boxes total 12.162.162,162.
 *
 * Two decimals truncated that fraction, and 9 x 1.351.351,35 = 12.162.162,15
 * still rounds to the same rupiah — but typing the printed 1.351.351 gives
 * 12.162.159, three rupiah short. Four decimals keep the fraction intact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_price', 15, 4)->change();
            $table->decimal('current_price', 15, 4)->nullable()->change();
            $table->decimal('subtotal', 15, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('subtotal', 12, 2)->change();
            $table->decimal('current_price', 12, 2)->nullable()->change();
            $table->decimal('unit_price', 12, 2)->change();
        });
    }
};
