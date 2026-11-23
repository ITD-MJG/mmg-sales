<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'view_sales_dashboard';

    /**
     * The Sales dashboard carries targets, the leaderboard and team-wide rep
     * figures, so it belongs to the sales and marketing ladder rather than to
     * every authenticated user. Unlike the report permissions these cannot be
     * derived from an existing permission: the whole point is that a rep who
     * has no report access still gets their own dashboard.
     *
     * Names must match a role row that exists; missing ones are skipped so the
     * migration is safe on environments whose roles have drifted.
     */
    private const ROLES = [
        'Sales Staff',
        'Sales Representative',
        'Sales Supervisor',
        'Sales Supervisor Clinical Diagnostic',
        'Sales Supervisor Life Science',
        'Area Sales Manager',
        'Regional Sales Manager',
        'Sales Manager',
        'Marketing Staff',
        'Marketing Manager',
        'Management Director',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::findOrCreate(self::PERMISSION);

        Role::whereIn('name', self::ROLES)
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
