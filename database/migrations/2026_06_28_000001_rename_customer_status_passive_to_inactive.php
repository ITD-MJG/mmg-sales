<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE customers SET status = 'inactive' WHERE status = 'passive'");

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE customers MODIFY COLUMN status ENUM('active','inactive') DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::statement("UPDATE customers SET status = 'passive' WHERE status = 'inactive'");

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE customers MODIFY COLUMN status ENUM('active','passive') DEFAULT 'active'");
    }
};
