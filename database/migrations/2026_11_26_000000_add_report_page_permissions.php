<?php

use App\Enums\Role as RoleEnum;
use App\Models\Department;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Per-page permissions for the Reports navigation group.
 *
 * The report pages used to share the dashboard-facing permissions
 * (view_sales_reports and friends), which are held by sales managers and the
 * product ladder. Those pages are now Management-only, so they need
 * permissions of their own; the old ones keep gating the dashboards.
 *
 * Recipients are derived from the role's department instead of a hardcoded
 * role list: production holds a single Management role named "Director", while
 * the seeder creates "Management Director".
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'view_report_sales',
        'view_report_products',
        'view_report_customers',
        'view_report_leads',
        'view_report_targets',
        'view_report_target_achievement',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permissionName) {
            Permission::findOrCreate($permissionName);
        }

        Role::query()
            ->where('name', RoleEnum::ManagementDirector->value)
            ->orWhereHas('department', fn ($query) => $query->where('name', Department::MANAGEMENT))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo(self::PERMISSIONS));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', self::PERMISSIONS)->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
