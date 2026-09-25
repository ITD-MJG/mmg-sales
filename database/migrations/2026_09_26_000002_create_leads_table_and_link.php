<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // M1's SQLite branch renamed the `leads` table and its `lead_code` column,
            // but SQLite keeps the original index name, so `leads_lead_code_unique`
            // now sits on `opportunities`. SQLite index names are database-global, and
            // the new thin `leads` table needs that name, so rehome it first.
            Schema::table('opportunities', function (Blueprint $table) {
                $table->renameIndex('leads_lead_code_unique', 'opportunities_opportunity_code_unique');
            });
        }

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_code', 20)->unique()->nullable();
            $table->string('title');
            $table->string('customer_name');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_person')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('phone');
            $table->enum('status', ['new', 'contacted', 'converted', 'disqualified'])->default('new');
            $table->enum('source', ['website', 'referral', 'cold_call', 'trade_show', 'partner', 'other'])->default('other');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->text('notes')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('disqualified_at')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['assigned_to']);
        });

        Schema::table('opportunities', function (Blueprint $table) {
            $table->foreignId('converted_from_lead_id')->nullable()->after('customer_id')
                ->constrained('leads')->nullOnDelete();
        });

        // activities.opportunity_id already exists (renamed in M1) and already carries its
        // own index + FK against opportunities. M2 only adds the fresh lead_id column and
        // its FK to the new table.
        Schema::table('activities', function (Blueprint $table) {
            $table->foreignId('lead_id')->nullable()->after('opportunity_id')
                ->constrained('leads')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
            $table->dropColumn('lead_id');
        });

        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropForeign(['converted_from_lead_id']);
            $table->dropColumn('converted_from_lead_id');
        });

        Schema::dropIfExists('leads');

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('opportunities', function (Blueprint $table) {
                $table->renameIndex('opportunities_opportunity_code_unique', 'leads_lead_code_unique');
            });
        }
    }
};
