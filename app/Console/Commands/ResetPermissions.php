<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class ResetPermissions extends Command
{
    protected $signature = 'permissions:reset';

    protected $description = 'Reset all roles and permissions to match RolesAndPermissionsSeeder without wiping the database';

    public function handle(): int
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->createPermissions();
        $this->assignPermissions();
        $this->reassignUsers();
        $this->deleteOldRoles();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->info('Permissions reset complete.');

        return Command::SUCCESS;
    }

    private function createPermissions(): void
    {
        $this->line('Creating permissions...');

        $models = [
            'customer_group', 'sub_segment', 'sales_type', 'customer', 'department',
            'distributor', 'activity', 'contact', 'product', 'lead', 'segment',
            'territory', 'position', 'principal', 'milestone', 'item', 'order',
            'target', 'user',
        ];

        $actions = ['view', 'view_any', 'create', 'update', 'delete', 'restore', 'force_delete'];

        $count = 0;
        foreach ($models as $model) {
            foreach ($actions as $action) {
                Permission::findOrCreate("{$action}_{$model}");
                $count++;
            }
        }

        $reportPermissions = [
            'view_sales_reports',
            'view_customer_reports',
            'view_product_reports',
            'view_project_reports',
            'view_lead_reports',
            'view_target_reports',
        ];

        foreach ($reportPermissions as $permission) {
            Permission::findOrCreate($permission);
            $count++;
        }

        $this->info("  Created/verified {$count} permissions.");
    }

    private function assignPermissions(): void
    {
        $this->line('Assigning permissions to roles...');

        $viewReference = [
            'view_any_customer', 'view_customer',
            'view_any_customer_group', 'view_customer_group',
            'view_any_contact', 'view_contact',
            'view_any_segment', 'view_segment',
            'view_any_sub_segment', 'view_sub_segment',
            'view_any_territory', 'view_territory',
            'view_any_distributor', 'view_distributor',
            'view_any_principal', 'view_principal',
            'view_any_product', 'view_product',
            'view_any_sales_type', 'view_sales_type',
            'view_any_item', 'view_item',
            'view_any_department', 'view_department',
            'view_any_position', 'view_position',
        ];

        $reportPermissions = [
            'view_sales_reports',
            'view_customer_reports',
            'view_product_reports',
            'view_project_reports',
            'view_lead_reports',
            'view_target_reports',
        ];

        // 1. Super Admin — no permissions (Gate::before bypass)
        Role::findOrCreate('Super Admin');
        $this->info('  Super Admin: bypass (no permissions)');

        // 2. Sales Admin: CRUD Customer, Customer Group, Contact, Segment, Sub Segment
        $adminSalesPerms = array_merge([
            'view_any_customer', 'view_customer', 'create_customer', 'update_customer', 'delete_customer', 'restore_customer', 'force_delete_customer',
            'view_any_customer_group', 'view_customer_group', 'create_customer_group', 'update_customer_group', 'delete_customer_group', 'restore_customer_group', 'force_delete_customer_group',
            'view_any_contact', 'view_contact', 'create_contact', 'update_contact', 'delete_contact', 'restore_contact', 'force_delete_contact',
            'view_any_segment', 'view_segment', 'create_segment', 'update_segment', 'delete_segment', 'restore_segment', 'force_delete_segment',
            'view_any_sub_segment', 'view_sub_segment', 'create_sub_segment', 'update_sub_segment', 'delete_sub_segment', 'restore_sub_segment', 'force_delete_sub_segment',
        ], $viewReference);

        Role::findOrCreate('Sales Admin')->syncPermissions($adminSalesPerms);
        $this->info('  Sales Admin: '.count($adminSalesPerms).' permissions');

        // 3. Import & Purchasing Supervisor: CRUD Principal, Product, Distributor
        $supIpPerms = array_merge([
            'view_any_principal', 'view_principal', 'create_principal', 'update_principal', 'delete_principal', 'restore_principal', 'force_delete_principal',
            'view_any_product', 'view_product', 'create_product', 'update_product', 'delete_product', 'restore_product', 'force_delete_product',
            'view_any_distributor', 'view_distributor', 'create_distributor', 'update_distributor', 'delete_distributor', 'restore_distributor', 'force_delete_distributor',
        ], $viewReference);

        Role::findOrCreate('Import & Purchasing Supervisor')->syncPermissions($supIpPerms);
        $this->info('  Import & Purchasing Supervisor: '.count($supIpPerms).' permissions');

        // 4. Sales & Marketing dept roles: CRUD Lead, Activity, Order, Target + view reference
        $smPerms = array_merge([
            'view_any_lead', 'view_lead', 'create_lead', 'update_lead', 'delete_lead',
            'view_any_activity', 'view_activity', 'create_activity', 'update_activity', 'delete_activity',
            'view_any_order', 'view_order', 'create_order', 'update_order', 'delete_order',
            'view_any_target', 'view_target', 'create_target', 'update_target', 'delete_target',
        ], $viewReference);

        $smRoles = [
            'Sales Staff',
            'Sales Supervisor',
            'Sales Area Manager',
            'Sales Regional Manager',
            'Marketing Staff',
            'Marketing Manager',
            'Sales Manager',
        ];

        foreach ($smRoles as $roleName) {
            Role::findOrCreate($roleName)->syncPermissions($smPerms);
        }
        $this->info('  S&M dept roles ('.count($smRoles).' roles): '.count($smPerms).' permissions each');

        // 5. Management Director: reports + view reference
        $directorPerms = array_merge($reportPermissions, $viewReference);

        Role::findOrCreate('Management Director')->syncPermissions($directorPerms);
        $this->info('  Management Director: '.count($directorPerms).' permissions');

        // 6. Report access: add report perms on top of S&M perms for specific roles
        $reportRoles = [
            'Sales Regional Manager',
            'Sales Area Manager',
            'Marketing Manager',
            'Sales Manager',
        ];

        foreach ($reportRoles as $roleName) {
            Role::where('name', $roleName)->first()?->givePermissionTo($reportPermissions);
        }
        $this->info('  Added report permissions to: '.implode(', ', $reportRoles));
    }

    private function reassignUsers(): void
    {
        $this->line('Reassigning users to new roles...');

        // [old role name, department name, new role name]
        $mapping = [
            ['Staff',                  'Sales',               'Sales Staff'],
            ['Supervisor',             'Sales',               'Sales Supervisor'],
            ['Area Sales Manager',     'Sales',               'Sales Area Manager'],
            ['Regional Sales Manager', 'Sales',               'Sales Regional Manager'],
            ['Admin',                  'Sales',               'Sales Admin'],
            ['Staff',                  'Marketing',           'Marketing Staff'],
            ['Manager',                'Marketing',           'Marketing Manager'],
            ['Director',               'Management',          'Management Director'],
            ['Supervisor',             'Import & Purchasing', 'Import & Purchasing Supervisor'],
        ];

        // Track per-user reassignment (last match wins)
        $userNewRoles = [];

        foreach ($mapping as [$oldRole, $dept, $newRole]) {
            $role = Role::where('name', $oldRole)->first();
            if (! $role) {
                continue;
            }

            $users = $role->users()->whereHas('department', fn ($q) => $q->where('name', $dept))->get();

            foreach ($users as $user) {
                $userNewRoles[$user->id] = $newRole;
            }
        }

        $oldRoleNames = ['Admin', 'Director', 'Staff', 'Supervisor', 'Regional Sales Manager', 'Area Sales Manager', 'Manager'];

        foreach ($userNewRoles as $userId => $newRoleName) {
            $user = User::find($userId);
            if (! $user) {
                continue;
            }

            $user->roles()->whereIn('name', $oldRoleNames)->detach();
            $user->assignRole($newRoleName);

            $this->info("  {$user->name}: {$newRoleName}");
        }

        if (empty($userNewRoles)) {
            $this->info('  No users to reassign.');
        }
    }

    private function deleteOldRoles(): void
    {
        $this->line('Deleting old roles...');

        $oldRoles = ['Admin', 'Director', 'Staff', 'Supervisor', 'Regional Sales Manager', 'Area Sales Manager', 'Manager'];

        foreach ($oldRoles as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->delete();
                $this->info("  Deleted: {$roleName}");
            }
        }
    }
}
