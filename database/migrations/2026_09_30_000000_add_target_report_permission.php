<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'view_target_reports';

    /**
     * Roles that already hold every other report permission.
     */
    private const REPORT_ROLES = [
        'Super Admin',
        'Management Director',
        'Sales Regional Manager',
        'Sales Area Manager',
        'Marketing Manager',
        'Sales Manager',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::findOrCreate(self::PERMISSION);

        foreach (self::REPORT_ROLES as $roleName) {
            Role::where('name', $roleName)->first()?->givePermissionTo($permission);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', self::PERMISSION)->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
