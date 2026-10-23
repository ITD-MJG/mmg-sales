<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_product', function (Blueprint $table): void {
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('lead_product', function (Blueprint $table): void {
            $table->dropColumn(['quantity', 'unit_price']);
        });
    }
};
