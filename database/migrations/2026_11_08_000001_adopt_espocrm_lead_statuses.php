<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the three-value lead status with the EspoCRM vocabulary.
 *
 * EspoCRM's list is nine statuses; `Assigned` is deliberately absent because in
 * this system assignment is the lead_collaborators relation, not a pipeline
 * state — a lead can be assigned and be at Proposal at the same time.
 *
 * Existing rows are remapped to their nearest equivalent:
 *   new         -> new
 *   contacted   -> in_process
 *   disqualified-> closed_lost
 */
return new class extends Migration
{
    private const ESPO_STATUSES = [
        'new',
        'in_process',
        'prospecting',
        'qualification',
        'proposal',
        'negotiation',
        'closed_won',
        'closed_lost',
    ];

    private const LEGACY_STATUSES = ['new', 'contacted', 'disqualified'];

    public function up(): void
    {
        if (! Schema::hasTable('leads')) {
            return;
        }

        // Widen to a superset first: the remap below writes values the current
        // enum does not hold yet, and narrowing early would truncate them.
        $this->setEnum($this->superset());

        DB::table('leads')->where('status', 'contacted')->update(['status' => 'in_process']);
        DB::table('leads')->where('status', 'disqualified')->update(['status' => 'closed_lost']);

        $this->setEnum(self::ESPO_STATUSES);
    }

    public function down(): void
    {
        if (! Schema::hasTable('leads')) {
            return;
        }

        $this->setEnum($this->superset());

        // Only the three states the old enum can express are recoverable; every
        // middle stage collapses back to `contacted`, which is what it replaced.
        DB::table('leads')->where('status', 'in_process')->update(['status' => 'contacted']);
        DB::table('leads')->where('status', 'closed_lost')->update(['status' => 'disqualified']);
        DB::table('leads')
            ->whereIn('status', ['prospecting', 'qualification', 'proposal', 'negotiation', 'closed_won'])
            ->update(['status' => 'contacted']);

        $this->setEnum(self::LEGACY_STATUSES);
    }

    /**
     * Legacy and Espo values merged without duplicates: `new` appears in both,
     * and MariaDB rejects a duplicated ENUM value.
     *
     * @return list<string>
     */
    private function superset(): array
    {
        return array_values(array_unique([...self::LEGACY_STATUSES, ...self::ESPO_STATUSES]));
    }

    /**
     * @param  list<string>  $statuses
     */
    private function setEnum(array $statuses): void
    {
        $list = implode(',', array_map(fn (string $status): string => "'{$status}'", $statuses));

        DB::statement("ALTER TABLE leads MODIFY status ENUM({$list}) NOT NULL DEFAULT 'new'");
    }
};
