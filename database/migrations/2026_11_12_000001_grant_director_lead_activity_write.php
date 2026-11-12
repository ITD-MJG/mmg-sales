<?php

use App\Models\Department;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Let a Management-department Director create and edit Leads and Activities.
 *
 * The role was seeded as `Management Director` with a view-only bundle, then
 * renamed to `Director` in the Roles admin screen, so it is matched the same
 * way User::isManagementDirector() matches it: any role in the Management
 * department whose name contains "Director".
 *
 * Delete stays denied — BasePolicy::before() blocks the destructive abilities
 * for a global viewer regardless of what is granted here.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const PERMISSIONS = [
        'view_any_lead', 'view_lead', 'create_lead', 'update_lead',
        'view_any_activity', 'view_activity', 'create_activity', 'update_activity',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permissionName) {
            Permission::findOrCreate($permissionName);
        }

        $managementId = Department::where('name', Department::MANAGEMENT)->value('id');

        if (! $managementId) {
            return;
        }

        $roles = Role::where('department_id', $managementId)
            ->where('name', 'like', '%Director%')
            ->get();

        foreach ($roles as $role) {
            // givePermissionTo is additive: an operator's custom grants survive.
            $role->givePermissionTo(self::PERMISSIONS);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Grants are indistinguishable from ones an operator made by hand, so
        // there is nothing safe to revoke automatically.
    }
};
