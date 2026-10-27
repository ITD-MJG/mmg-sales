<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'product_acc_code')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->string('product_acc_code', 50)->nullable()->after('internal_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'product_acc_code')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropColumn('product_acc_code');
            });
        }
    }

};
