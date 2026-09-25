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
            $this->upSqlite();

            return;
        }

        // 1. Drop FK constraints that reference projects.id
        DB::statement('ALTER TABLE orders DROP FOREIGN KEY orders_project_id_foreign');
        DB::statement('ALTER TABLE activities DROP FOREIGN KEY activities_project_id_foreign');
        DB::statement('ALTER TABLE project_collaborators DROP FOREIGN KEY project_collaborators_project_id_foreign');
        DB::statement('ALTER TABLE project_product DROP FOREIGN KEY project_product_project_id_foreign');
        DB::statement('ALTER TABLE project_milestone DROP FOREIGN KEY project_milestone_project_id_foreign');
        DB::statement('ALTER TABLE projects DROP FOREIGN KEY projects_contact_person_foreign');

        // 2. Drop indexes before renaming columns
        DB::statement('ALTER TABLE activities DROP INDEX activities_project_id_performed_at_index');
        DB::statement('ALTER TABLE project_collaborators DROP INDEX project_collaborators_project_id_user_id_unique');

        // 3. Rename tables
        Schema::rename('projects', 'leads');
        Schema::rename('project_collaborators', 'lead_collaborators');
        Schema::rename('project_product', 'lead_product');
        Schema::rename('project_milestone', 'lead_milestone');

        // 4. Rename FK columns
        DB::statement('ALTER TABLE orders CHANGE project_id lead_id bigint(20) unsigned NULL');
        DB::statement('ALTER TABLE activities CHANGE project_id lead_id bigint(20) unsigned NULL');
        DB::statement('ALTER TABLE lead_collaborators CHANGE project_id lead_id bigint(20) unsigned NOT NULL');
        DB::statement('ALTER TABLE lead_product CHANGE project_id lead_id bigint(20) unsigned NOT NULL');
        DB::statement('ALTER TABLE lead_milestone CHANGE project_id lead_id bigint(20) unsigned NOT NULL');

        // 5. Add new indexes
        DB::statement('ALTER TABLE activities ADD INDEX activities_lead_id_performed_at_index (lead_id, performed_at)');
        DB::statement('ALTER TABLE lead_collaborators ADD UNIQUE lead_collaborators_lead_id_user_id_unique (lead_id, user_id)');

        // 6. Re-add FK constraints
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_lead_id_foreign FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE activities ADD CONSTRAINT activities_lead_id_foreign FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE lead_collaborators ADD CONSTRAINT lead_collaborators_lead_id_foreign FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE lead_product ADD CONSTRAINT lead_product_lead_id_foreign FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE lead_milestone ADD CONSTRAINT lead_milestone_lead_id_foreign FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE');

        // 7. Add lead_code, copy project_code values, then drop old columns
        Schema::table('leads', function ($table) {
            $table->string('lead_code', 20)->unique()->nullable()->after('id');
        });

        DB::statement('UPDATE leads SET lead_code = project_code WHERE lead_code IS NULL');

        Schema::table('leads', function ($table) {
            $table->dropColumn(['project_code', 'contact_person']);
        });
    }

    /**
     * SQLite cannot ALTER ... DROP FOREIGN KEY, but it rewrites foreign key
     * references automatically when tables and columns are renamed, so only
     * portable schema operations are needed.
     */
    private function upSqlite(): void
    {
        Schema::rename('projects', 'leads');
        Schema::rename('project_collaborators', 'lead_collaborators');
        Schema::rename('project_product', 'lead_product');
        Schema::rename('project_milestone', 'lead_milestone');

        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('project_id', 'lead_id');
        });
        Schema::table('activities', function (Blueprint $table) {
            $table->renameColumn('project_id', 'lead_id');
        });
        Schema::table('lead_collaborators', function (Blueprint $table) {
            $table->renameColumn('project_id', 'lead_id');
        });
        Schema::table('lead_product', function (Blueprint $table) {
            $table->renameColumn('project_id', 'lead_id');
        });
        Schema::table('lead_milestone', function (Blueprint $table) {
            $table->renameColumn('project_id', 'lead_id');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->index(['lead_id', 'performed_at'], 'activities_lead_id_performed_at_index');
        });
        Schema::table('lead_collaborators', function (Blueprint $table) {
            $table->unique(['lead_id', 'user_id'], 'lead_collaborators_lead_id_user_id_unique');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('lead_code', 20)->nullable();
        });

        DB::table('leads')->whereNull('lead_code')->update(['lead_code' => DB::raw('project_code')]);

        // SQLite keeps the original index name after a table rename, so the
        // stale unique index on project_code must go before the column does.
        Schema::table('leads', function (Blueprint $table) {
            $table->dropUnique('projects_project_code_unique');
        });

        // Dropping columns rebuilds the table on SQLite, so the unique index is
        // added afterwards to avoid the rebuild discarding it. The FK on
        // contact_person must be dropped first or the rebuild keeps it and
        // fails with "unknown column contact_person in foreign key definition".
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['contact_person']);
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['project_code', 'contact_person']);
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->unique('lead_code', 'leads_lead_code_unique');
        });
    }

    private function downSqlite(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropUnique('leads_lead_code_unique');
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->string('project_code', 20)->nullable();
            $table->unsignedBigInteger('contact_person')->nullable();
        });

        DB::table('leads')->whereNull('project_code')->update(['project_code' => DB::raw('lead_code')]);

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('lead_code');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->renameColumn('lead_id', 'project_id');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('lead_id', 'project_id');
        });
        Schema::table('lead_collaborators', function (Blueprint $table) {
            $table->renameColumn('lead_id', 'project_id');
        });
        Schema::table('lead_product', function (Blueprint $table) {
            $table->renameColumn('lead_id', 'project_id');
        });
        Schema::table('lead_milestone', function (Blueprint $table) {
            $table->renameColumn('lead_id', 'project_id');
        });

        Schema::rename('leads', 'projects');
        Schema::rename('lead_collaborators', 'project_collaborators');
        Schema::rename('lead_product', 'project_product');
        Schema::rename('lead_milestone', 'project_milestone');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->downSqlite();

            return;
        }
        // Reverse: add back project_code, copy lead_code values, drop lead_code
        Schema::table('leads', function ($table) {
            $table->string('project_code', 20)->unique()->nullable()->after('id');
            $table->unsignedBigInteger('contact_person')->nullable()->after('customer_id');
        });

        DB::statement('UPDATE leads SET project_code = lead_code WHERE project_code IS NULL');

        Schema::table('leads', function ($table) {
            $table->dropColumn('lead_code');
        });

        // Drop lead FK constraints
        DB::statement('ALTER TABLE orders DROP FOREIGN KEY orders_lead_id_foreign');
        DB::statement('ALTER TABLE activities DROP FOREIGN KEY activities_lead_id_foreign');
        DB::statement('ALTER TABLE lead_collaborators DROP FOREIGN KEY lead_collaborators_lead_id_foreign');
        DB::statement('ALTER TABLE lead_product DROP FOREIGN KEY lead_product_lead_id_foreign');
        DB::statement('ALTER TABLE lead_milestone DROP FOREIGN KEY lead_milestone_lead_id_foreign');

        // Drop new indexes
        DB::statement('ALTER TABLE activities DROP INDEX activities_lead_id_performed_at_index');
        DB::statement('ALTER TABLE lead_collaborators DROP INDEX lead_collaborators_lead_id_user_id_unique');

        // Rename columns back
        DB::statement('ALTER TABLE orders CHANGE lead_id project_id bigint(20) unsigned NULL');
        DB::statement('ALTER TABLE activities CHANGE lead_id project_id bigint(20) unsigned NULL');
        DB::statement('ALTER TABLE lead_collaborators CHANGE lead_id project_id bigint(20) unsigned NOT NULL');
        DB::statement('ALTER TABLE lead_product CHANGE lead_id project_id bigint(20) unsigned NOT NULL');
        DB::statement('ALTER TABLE lead_milestone CHANGE lead_id project_id bigint(20) unsigned NOT NULL');

        // Rename tables back
        Schema::rename('leads', 'projects');
        Schema::rename('lead_collaborators', 'project_collaborators');
        Schema::rename('lead_product', 'project_product');
        Schema::rename('lead_milestone', 'project_milestone');

        // Re-add original indexes
        DB::statement('ALTER TABLE activities ADD INDEX activities_project_id_performed_at_index (project_id, performed_at)');
        DB::statement('ALTER TABLE project_collaborators ADD UNIQUE project_collaborators_project_id_user_id_unique (project_id, user_id)');

        // Re-add original FK constraints
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_project_id_foreign FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE activities ADD CONSTRAINT activities_project_id_foreign FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE project_collaborators ADD CONSTRAINT project_collaborators_project_id_foreign FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE project_product ADD CONSTRAINT project_product_project_id_foreign FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE project_milestone ADD CONSTRAINT project_milestone_project_id_foreign FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_contact_person_foreign FOREIGN KEY (contact_person) REFERENCES contacts(id) ON DELETE SET NULL');
    }
};
