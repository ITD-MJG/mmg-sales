<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'department_id')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            try {
                $table->dropForeign(['department_id']);
            } catch (Throwable $e) {
                // Constraint name varies by driver and may already be gone.
            }

            $table->dropColumn('department_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'department_id')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->after('bulan')
                ->constrained()->nullOnDelete();
        });
    }
};
