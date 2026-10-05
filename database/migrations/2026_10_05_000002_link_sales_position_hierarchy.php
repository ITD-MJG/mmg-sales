<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Link the Sales position ladder so managers resolve their teams.
 *
 * `Position::getAllDescendantIds()` walks `parent_id`. In production 18 of 21
 * positions had `parent_id = NULL`, so that walk returned only the position
 * itself and no manager could resolve a subordinate through the position
 * hierarchy. This links the Sales branch.
 *
 * The ladder already exists and is level-consistent:
 *
 *   Managing Director (0) -> Director (1) -> Regional Sales Manager (2)
 *     -> Area Sales Manager (3) -> Sales Supervisor (4) -> Sales Rep (5)
 *
 * Only the Supervisor -> Area Sales Manager edges are missing. Both supervisor
 * positions sit at level 4 under an Area Sales Manager at level 3.
 *
 * `Sales Representative` is deliberately left unlinked. All ten reps share one
 * position while two separate supervisor positions exist, so a single
 * `parent_id` cannot represent the real reporting split. Linking it to either
 * supervisor would silently hide the other supervisor's team. Splitting the rep
 * position per supervisor is a follow-up that needs per-user reassignment.
 *
 * A row is only relinked when its current `parent_id` is null, so re-running
 * this cannot clobber a link an operator has since corrected by hand.
 */
return new class extends Migration
{
    /**
     * Child position name => parent position name.
     */
    private const LINKS = [
        'Sales Supervisor Clinical Diagnostic' => 'Area Sales Manager',
        'Sales Supervisor Life Science' => 'Area Sales Manager',
    ];

    public function up(): void
    {
        foreach (self::LINKS as $childName => $parentName) {
            $child = DB::table('positions')->where('name', $childName)->first();
            $parent = DB::table('positions')->where('name', $parentName)->first();

            if (! $child || ! $parent) {
                continue;
            }

            // Never override an existing link — an operator may have corrected
            // this by hand, and that intent should win.
            if ($child->parent_id !== null) {
                continue;
            }

            // Guard against creating a cycle if the data has been reorganised.
            if ($this->wouldCycle((int) $child->id, (int) $parent->id)) {
                continue;
            }

            DB::table('positions')->where('id', $child->id)->update([
                'parent_id' => $parent->id,
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Whether making $parentId the parent of $childId would create a loop.
     */
    private function wouldCycle(int $childId, int $parentId): bool
    {
        $seen = [];
        $cursor = $parentId;

        while ($cursor !== null && ! isset($seen[$cursor])) {
            if ($cursor === $childId) {
                return true;
            }

            $seen[$cursor] = true;
            $cursor = DB::table('positions')->where('id', $cursor)->value('parent_id');
            $cursor = $cursor === null ? null : (int) $cursor;
        }

        return false;
    }

    /**
     * Unlink the edges this migration created.
     */
    public function down(): void
    {
        foreach (array_keys(self::LINKS) as $childName) {
            $child = DB::table('positions')->where('name', $childName)->first();

            if (! $child) {
                continue;
            }

            $parentId = DB::table('positions')
                ->where('name', self::LINKS[$childName])
                ->value('id');

            if ($parentId !== null && (int) $child->parent_id === (int) $parentId) {
                DB::table('positions')->where('id', $child->id)->update([
                    'parent_id' => null,
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
