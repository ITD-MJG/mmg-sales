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

        // 1. Drop FKs referencing leads.id
        DB::statement('ALTER TABLE orders DROP FOREIGN KEY orders_lead_id_foreign');
        DB::statement('ALTER TABLE activities DROP FOREIGN KEY activities_lead_id_foreign');
        DB::statement('ALTER TABLE lead_collaborators DROP FOREIGN KEY lead_collaborators_lead_id_foreign');
        DB::statement('ALTER TABLE lead_product DROP FOREIGN KEY lead_product_lead_id_foreign');
        DB::statement('ALTER TABLE lead_milestone DROP FOREIGN KEY lead_milestone_lead_id_foreign');

        // 2. Drop indexes before renaming columns
        DB::statement('ALTER TABLE activities DROP INDEX activities_lead_id_performed_at_index');
        DB::statement('ALTER TABLE lead_collaborators DROP INDEX lead_collaborators_lead_id_user_id_unique');

        // 3. Drop the dead milestone tables (verified empty on production)
        Schema::dropIfExists('lead_milestone');
        Schema::dropIfExists('milestones');

        // 4. Rename tables
        Schema::rename('leads', 'opportunities');
        Schema::rename('lead_collaborators', 'opportunity_collaborators');
        Schema::rename('lead_product', 'opportunity_product');

        // 5. Rename columns
        DB::statement('ALTER TABLE opportunities CHANGE lead_code opportunity_code varchar(20) NULL');
        DB::statement('ALTER TABLE opportunities CHANGE status stage enum("new","contacted","qualified","proposal","negotiation","won","lost") NOT NULL DEFAULT "new"');
        DB::statement('ALTER TABLE opportunity_collaborators CHANGE lead_id opportunity_id bigint(20) unsigned NOT NULL');
        DB::statement('ALTER TABLE opportunity_product CHANGE lead_id opportunity_id bigint(20) unsigned NOT NULL');
        DB::statement('ALTER TABLE orders CHANGE lead_id opportunity_id bigint(20) unsigned NULL');
        // Every existing activity points at a row that just became an opportunity,
        // so its FK column renames too. A fresh nullable lead_id is added in M2.
        DB::statement('ALTER TABLE activities CHANGE lead_id opportunity_id bigint(20) unsigned NULL');

        // 6. Re-add indexes
        DB::statement('ALTER TABLE activities ADD INDEX activities_opportunity_id_performed_at_index (opportunity_id, performed_at)');
        DB::statement('ALTER TABLE opportunity_collaborators ADD UNIQUE opportunity_collaborators_opportunity_id_user_id_unique (opportunity_id, user_id)');

        // 7. Re-add FKs
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_opportunity_id_foreign FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE activities ADD CONSTRAINT activities_opportunity_id_foreign FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE opportunity_collaborators ADD CONSTRAINT opportunity_collaborators_opportunity_id_foreign FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE opportunity_product ADD CONSTRAINT opportunity_product_opportunity_id_foreign FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE');
    }

    private function upSqlite(): void
    {
        Schema::dropIfExists('lead_milestone');
        Schema::dropIfExists('milestones');

        Schema::rename('leads', 'opportunities');
        Schema::rename('lead_collaborators', 'opportunity_collaborators');
        Schema::rename('lead_product', 'opportunity_product');

        Schema::table('opportunities', function (Blueprint $table) {
            $table->renameColumn('lead_code', 'opportunity_code');
            $table->renameColumn('status', 'stage');
        });
        Schema::table('opportunity_collaborators', function (Blueprint $table) {
            $table->renameColumn('lead_id', 'opportunity_id');
        });
        Schema::table('opportunity_product', function (Blueprint $table) {
            $table->renameColumn('lead_id', 'opportunity_id');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('lead_id', 'opportunity_id');
        });
        Schema::table('activities', function (Blueprint $table) {
            $table->renameColumn('lead_id', 'opportunity_id');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('activities', function (Blueprint $table) {
                $table->renameColumn('opportunity_id', 'lead_id');
            });
            Schema::table('orders', function (Blueprint $table) {
                $table->renameColumn('opportunity_id', 'lead_id');
            });
            Schema::table('opportunity_product', function (Blueprint $table) {
                $table->renameColumn('opportunity_id', 'lead_id');
            });
            Schema::table('opportunity_collaborators', function (Blueprint $table) {
                $table->renameColumn('opportunity_id', 'lead_id');
            });
            Schema::table('opportunities', function (Blueprint $table) {
                $table->renameColumn('opportunity_code', 'lead_code');
                $table->renameColumn('stage', 'status');
            });
            Schema::rename('opportunity_product', 'lead_product');
            Schema::rename('opportunity_collaborators', 'lead_collaborators');
            Schema::rename('opportunities', 'leads');

            return;
        }

        DB::statement('ALTER TABLE orders DROP FOREIGN KEY orders_opportunity_id_foreign');
        DB::statement('ALTER TABLE activities DROP FOREIGN KEY activities_opportunity_id_foreign');
        DB::statement('ALTER TABLE opportunity_collaborators DROP FOREIGN KEY opportunity_collaborators_opportunity_id_foreign');
        DB::statement('ALTER TABLE opportunity_product DROP FOREIGN KEY opportunity_product_opportunity_id_foreign');

        DB::statement('ALTER TABLE activities DROP INDEX activities_opportunity_id_performed_at_index');
        DB::statement('ALTER TABLE opportunity_collaborators DROP INDEX opportunity_collaborators_opportunity_id_user_id_unique');

        DB::statement('ALTER TABLE activities CHANGE opportunity_id lead_id bigint(20) unsigned NULL');
        DB::statement('ALTER TABLE orders CHANGE opportunity_id lead_id bigint(20) unsigned NULL');
        DB::statement('ALTER TABLE opportunity_product CHANGE opportunity_id lead_id bigint(20) unsigned NOT NULL');
        DB::statement('ALTER TABLE opportunity_collaborators CHANGE opportunity_id lead_id bigint(20) unsigned NOT NULL');
        DB::statement('ALTER TABLE opportunities CHANGE stage status enum("new","contacted","qualified","proposal","negotiation","won","lost") NOT NULL DEFAULT "new"');
        DB::statement('ALTER TABLE opportunities CHANGE opportunity_code lead_code varchar(20) NULL');

        Schema::rename('opportunity_product', 'lead_product');
        Schema::rename('opportunity_collaborators', 'lead_collaborators');
        Schema::rename('opportunities', 'leads');

        DB::statement('ALTER TABLE activities ADD INDEX activities_lead_id_performed_at_index (lead_id, performed_at)');
        DB::statement('ALTER TABLE lead_collaborators ADD UNIQUE lead_collaborators_lead_id_user_id_unique (lead_id, user_id)');

        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_lead_id_foreign FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE activities ADD CONSTRAINT activities_lead_id_foreign FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE lead_collaborators ADD CONSTRAINT lead_collaborators_lead_id_foreign FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE lead_product ADD CONSTRAINT lead_product_lead_id_foreign FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE');

        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('weight');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('lead_milestone', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('milestone_id')->constrained()->restrictOnDelete();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
};
