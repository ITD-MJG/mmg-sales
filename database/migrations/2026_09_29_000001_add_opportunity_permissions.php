<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The opportunity permissions mirror the existing lead permissions.
     * Roles are granted by inspecting what each role already holds for
     * `lead`, so this works on environments whose role names or grants
     * have drifted from the seeder.
     */
    private array $actions = ['view', 'view_any', 'create', 'update', 'delete'];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->actions as $action) {
            Permission::findOrCreate("{$action}_opportunity");
        }

        foreach (Role::with('permissions')->get() as $role) {
            $held = $role->permissions->pluck('name');
            $granted = [];

            foreach ($this->actions as $action) {
                if ($held->contains("{$action}_lead")) {
                    $granted[] = "{$action}_opportunity";
                }
            }

            if ($granted !== []) {
                $role->givePermissionTo($granted);
            }
        }
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', 'like', '%_opportunity')->delete();
    }
};
