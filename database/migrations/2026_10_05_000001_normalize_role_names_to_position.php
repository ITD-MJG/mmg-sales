<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalise role names to the position name, and nothing else.
 *
 * Four formats have existed in this codebase:
 *
 *   1. bare position name            "Area Sales Manager"
 *   2. "{Position} - {Department}"   "Area Sales Manager - Sales"
 *   3. "{Department} {Position}"     "Sales Area Sales Manager"
 *   4. assorted legacy literals      "Sales Regional Manager"
 *
 * Migration 2026_06_23_090842 wrote format 2 into production, while the app
 * checks the bare position name. Every role check therefore failed silently
 * for the whole sales team: a regional manager, an area manager and the
 * supervisor who owns most of the leads all fell through to the
 * own-records-only fallback and could not see their teams.
 *
 * Format 1 is canonical. RoleResource::generateRoleName() emits it, and this
 * migration moves existing rows onto it.
 *
 * Roles whose name is not a known drifted alias are left untouched. Only the
 * four sales roles plus their legacy bare aliases are rewritten, because
 * guessing a target name for an unrelated role risks merging two distinct
 * roles together.
 */
return new class extends Migration
{
    /**
     * Drifted role name => canonical position name.
     *
     * Every target must be an existing row in `positions`; the migration
     * verifies this and skips any mapping whose target has been renamed or
     * removed, rather than inventing a role.
     */
    private const RENAME_MAP = [
        'Sales Representative - Sales' => 'Sales Representative',
        'Sales Supervisor Clinical Diagnostic - Sales' => 'Sales Supervisor Clinical Diagnostic',
        'Area Sales Manager - Sales' => 'Area Sales Manager',
        'Regional Sales Manager - Sales' => 'Regional Sales Manager',

        // Legacy bare aliases. These are the strings the application checks
        // today, so a role still carrying one is renamed onto the real
        // position it was always meant to represent.
        'Sales Staff' => 'Sales Representative',
        'Sales Supervisor' => 'Sales Supervisor Clinical Diagnostic',
        'Sales Area Manager' => 'Area Sales Manager',
        'Sales Regional Manager' => 'Regional Sales Manager',
    ];

    public function up(): void
    {
        $positionNames = DB::table('positions')->pluck('name')->all();

        foreach (self::RENAME_MAP as $from => $to) {
            $role = DB::table('roles')->where('name', $from)->first();

            if (! $role) {
                continue;
            }

            if (! in_array($to, $positionNames, true)) {
                // The target position no longer exists. Leave the role alone
                // and let an operator decide, rather than creating a role that
                // does not correspond to anything.
                continue;
            }

            $existing = DB::table('roles')->where('name', $to)->where('id', '!=', $role->id)->first();

            if ($existing) {
                $this->mergeRole($role, $existing);
            } else {
                DB::table('roles')->where('id', $role->id)->update([
                    'name' => $to,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Move every user and permission off $from and onto $to, then delete $from.
     *
     * The pivot tables are keyed on (role_id, model/model_id) and
     * (permission_id, role_id) with no unique index on the pair combination
     * used here, so each insert is guarded by an existence check.
     */
    private function mergeRole(object $from, object $to): void
    {
        $assignments = DB::table('model_has_roles')->where('role_id', $from->id)->get();

        foreach ($assignments as $assignment) {
            $alreadyAssigned = DB::table('model_has_roles')
                ->where('role_id', $to->id)
                ->where('model_type', $assignment->model_type)
                ->where('model_id', $assignment->model_id)
                ->exists();

            if (! $alreadyAssigned) {
                DB::table('model_has_roles')->insert([
                    'role_id' => $to->id,
                    'model_type' => $assignment->model_type,
                    'model_id' => $assignment->model_id,
                ]);
            }
        }

        DB::table('model_has_roles')->where('role_id', $from->id)->delete();

        $permissions = DB::table('role_has_permissions')->where('role_id', $from->id)->pluck('permission_id');

        foreach ($permissions as $permissionId) {
            $alreadyGranted = DB::table('role_has_permissions')
                ->where('role_id', $to->id)
                ->where('permission_id', $permissionId)
                ->exists();

            if (! $alreadyGranted) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $permissionId,
                    'role_id' => $to->id,
                ]);
            }
        }

        DB::table('role_has_permissions')->where('role_id', $from->id)->delete();
        DB::table('roles')->where('id', $from->id)->delete();
    }

    /**
     * Restore the drifted names.
     *
     * Reverses only the renames this migration made and that it can still
     * identify: a role whose current name is a canonical target and which
     * still has a corresponding "{Position} - {Department}" predecessor.
     * Irreversible merges are not undone — the original row id is gone.
     */
    public function down(): void
    {
        $reverse = [
            'Sales Representative' => 'Sales Representative - Sales',
            'Sales Supervisor Clinical Diagnostic' => 'Sales Supervisor Clinical Diagnostic - Sales',
            'Area Sales Manager' => 'Area Sales Manager - Sales',
            'Regional Sales Manager' => 'Regional Sales Manager - Sales',
        ];

        foreach ($reverse as $current => $drifted) {
            $role = DB::table('roles')->where('name', $current)->first();

            if (! $role) {
                continue;
            }

            if (DB::table('roles')->where('name', $drifted)->exists()) {
                continue;
            }

            DB::table('roles')->where('id', $role->id)->update([
                'name' => $drifted,
                'updated_at' => now(),
            ]);
        }
    }
};
