<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'view_target_reports';

    /**
     * The target report sits alongside the other reports, so it goes to whoever
     * already has sales report access. Deriving the recipients from the existing
     * permission rather than a hardcoded role list keeps this correct on
     * environments whose role names have drifted from the seeder.
     */
    private const SOURCE_PERMISSION = 'view_sales_reports';

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::findOrCreate(self::PERMISSION);

        Role::permission(self::SOURCE_PERMISSION)
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', self::PERMISSION)->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
