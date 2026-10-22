<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('leads', 'assigned_to')) {
            return;
        }

        DB::table('lead_collaborators')->insertUsing(
            ['lead_id', 'user_id', 'added_by', 'created_at', 'updated_at'],
            DB::table('leads')
                ->whereNotNull('assigned_to')
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('lead_collaborators')
                        ->whereColumn('lead_collaborators.lead_id', 'leads.id')
                        ->whereColumn('lead_collaborators.user_id', 'leads.assigned_to');
                })
                ->selectRaw('id, assigned_to, created_by, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP')
        );

        Schema::table('leads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_to');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('leads', 'assigned_to')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table): void {
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
        });

        // Collaborators are peers, so a former primary assignee cannot be
        // selected reliably for restoration to this single-user column.
    }
};
