<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_product', function (Blueprint $table): void {
            $table->foreignId('principal_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });

        DB::table('lead_product')
            ->join('products', 'lead_product.product_id', '=', 'products.id')
            ->whereNull('lead_product.principal_id')
            ->update(['lead_product.principal_id' => DB::raw('products.principal_id')]);
    }

    public function down(): void
    {
        Schema::table('lead_product', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('principal_id');
        });
    }
};
