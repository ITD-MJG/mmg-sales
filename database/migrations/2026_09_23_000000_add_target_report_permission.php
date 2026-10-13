<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::findOrCreate('view_target_reports');

        // Grant to every role that can already see sales reports, so target
        // reporting follows the same audience without duplicating role names.
        $roles = Role::whereHas('permissions', function ($query) {
            $query->where('name', 'view_sales_reports');
        })->get();

        foreach ($roles as $role) {
            $role->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', 'view_target_reports')->delete();
    }
};
