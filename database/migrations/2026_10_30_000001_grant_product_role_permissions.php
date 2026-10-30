<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Give the product-ladder roles the permissions they were created without.
 *
 * `Product Manager`, `Jr Product Manager` and `Product Executive` were created
 * by the Roles admin screen and never received a permission set, so any user
 * assigned one of them rendered an empty navigation — only the Dashboard.
 *
 * Nothing granted them either: RolesAndPermissionsSeeder and permissions:reset
 * only manage the Sales/Marketing generic, Management Director, Import &
 * Purchasing, Logistics and Finance roles, and the only migration that ever
 * tried (2026_03_09_010453_add_report_permissions) looked up the legacy names
 * ProductManager / JrProductManager / ProductExecutive, none of which exist.
 * Every lookup returned null and was skipped silently.
 *
 * The list below is the bundle the product ladder already runs on in
 * production: `Jr. Product Manager` and `Sr. Product Manager` hold it verbatim
 * (53 permissions, verified identical). It is spelled out rather than copied
 * from a live role so the migration behaves the same on every database.
 *
 * Canonical names come from App\Enums\Role; RolesTable writes Position::name.
 */
return new class extends Migration
{
    /**
     * The shared product-ladder permission bundle.
     *
     * @var list<string>
     */
    private const PRODUCT_PERMISSIONS = [
        // Lead
        'view_any_lead', 'view_lead', 'create_lead', 'update_lead', 'delete_lead',
        // Opportunity
        'view_any_opportunity', 'view_opportunity', 'create_opportunity', 'update_opportunity', 'delete_opportunity',
        // Activity
        'view_any_activity', 'view_activity', 'create_activity', 'update_activity', 'delete_activity',
        // Activity comment
        'view_any_activity_comment', 'view_activity_comment', 'create_activity_comment', 'update_activity_comment', 'delete_activity_comment',
        'restore_activity_comment', 'force_delete_activity_comment',
        // Order
        'view_any_order', 'view_order', 'create_order', 'update_order', 'delete_order',
        // Target
        'view_any_target', 'view_target', 'create_target', 'update_target', 'delete_target',
        // Reference data
        'view_any_item', 'view_item',
        'view_any_position', 'view_position',
        'view_any_sales_type', 'view_sales_type',
        'view_any_segment', 'view_segment',
        'view_any_sub_segment', 'view_sub_segment',
        'view_any_territory', 'view_territory',
        // Reports
        'view_sales_reports', 'view_product_reports', 'view_customer_reports',
        'view_project_reports', 'view_lead_reports', 'view_target_reports',
        // Dashboard widgets
        'view_monthly_revenue_widget', 'view_principal_revenue_widget', 'view_top_selling_products_widget',
    ];

    /**
     * Product roles that must hold the shared bundle.
     *
     * @var list<string>
     */
    private const PRODUCT_ROLES = [
        'Product Executive',
        'Jr Product Manager',
        'Product Manager',
        'Jr. Product Manager',
        'Sr. Product Manager',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // findOrCreate keeps this runnable on a database that has not created
        // every permission yet, and makes a re-run a no-op.
        foreach (self::PRODUCT_PERMISSIONS as $permissionName) {
            Permission::findOrCreate($permissionName);
        }

        foreach (self::PRODUCT_ROLES as $roleName) {
            $role = Role::where('name', $roleName)->first();

            if (! $role) {
                continue;
            }

            // Only fill roles that are actually empty. An operator may have
            // customised a product role, and syncPermissions would destroy that.
            if ($role->permissions()->exists()) {
                continue;
            }

            $role->syncPermissions(self::PRODUCT_PERMISSIONS);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Permissions granted here are indistinguishable from ones an operator
        // assigned by hand, so there is nothing safe to revoke automatically.
    }
};
