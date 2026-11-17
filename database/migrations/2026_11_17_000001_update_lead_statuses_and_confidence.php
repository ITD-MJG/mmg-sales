<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the EspoCRM lead statuses with the ten-stage sales pipeline.
 *
 * Existing rows are remapped to their nearest equivalent:
 *   in_process   -> prospect
 *   prospecting  -> prospect
 *   proposal     -> quotation
 *   negotiation  -> negotiate
 *
 * `closed_won` and `closed_lost` keep their keys so terminal-state checks and
 * historical data stay valid; only their labels change (Win / Lost).
 */
return new class extends Migration
{
    private const NEW_STATUSES = [
        'new',
        'prospect',
        'qualification',
        'quotation',
        'convince',
        'negotiate',
        'decision_maker',
        'closed_won',
        'closed_lost',
        'hold',
    ];

    private const OLD_STATUSES = [
        'new',
        'in_process',
        'prospecting',
        'qualification',
        'proposal',
        'negotiation',
        'closed_won',
        'closed_lost',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('leads')) {
            return;
        }

        // Widen to a superset first: the remap below writes values the current
        // enum does not hold yet, and narrowing early would truncate them.
        $this->setEnum($this->superset());

        DB::table('leads')->whereIn('status', ['in_process', 'prospecting'])->update(['status' => 'prospect']);
        DB::table('leads')->where('status', 'proposal')->update(['status' => 'quotation']);
        DB::table('leads')->where('status', 'negotiation')->update(['status' => 'negotiate']);

        $this->setEnum(self::NEW_STATUSES);
    }

    public function down(): void
    {
        if (! Schema::hasTable('leads')) {
            return;
        }

        $this->setEnum($this->superset());

        DB::table('leads')->where('status', 'prospect')->update(['status' => 'in_process']);
        DB::table('leads')->where('status', 'quotation')->update(['status' => 'proposal']);
        DB::table('leads')->where('status', 'negotiate')->update(['status' => 'negotiation']);
        DB::table('leads')
            ->whereIn('status', ['convince', 'decision_maker', 'hold'])
            ->update(['status' => 'in_process']);

        $this->setEnum(self::OLD_STATUSES);
    }

    /**
     * @return list<string>
     */
    private function superset(): array
    {
        return array_values(array_unique([...self::OLD_STATUSES, ...self::NEW_STATUSES]));
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
