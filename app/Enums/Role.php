<?php

namespace App\Enums;

/**
 * The canonical role names.
 *
 * A role name IS a position name, and nothing else. Four formats have existed:
 * "{Position} - {Department}", "{Department} {Position}", the bare position
 * name, and assorted one-off literals. Production ran on the first while the
 * code checked the third, so every role check failed silently and the whole
 * sales team lost manager visibility of their teams.
 *
 * The department is deliberately not part of the name. It is already a column
 * on the role, and prefixing it produced "Sales Sales Supervisor".
 *
 * Values here must match a row in `positions`. Migration
 * 2026_10_05_000001_normalize_role_names_to_position aligns existing data.
 */
enum Role: string
{
    case SuperAdmin = 'Super Admin';

    // Sales
    case SalesAdmin = 'Sales Admin';
    case SalesManager = 'Sales Manager';
    case RegionalSalesManager = 'Regional Sales Manager';
    case AreaSalesManager = 'Area Sales Manager';
    case SalesSupervisorClinicalDiagnostic = 'Sales Supervisor Clinical Diagnostic';
    case SalesSupervisorLifeScience = 'Sales Supervisor Life Science';
    case SalesRepresentative = 'Sales Representative';

    // Marketing
    case MarketingManager = 'Marketing Manager';
    case MarketingStaff = 'Marketing Staff';
    case ProductManager = 'Product Manager';
    case JuniorProductManager = 'Jr Product Manager';
    case ProductExecutive = 'Product Executive';

    // Management
    case ManagementDirector = 'Management Director';

    // Import & Purchasing
    case ImportPurchasingSupervisor = 'Import & Purchasing Supervisor';

    // Finance & Accounting
    case FinanceAccountingSupervisor = 'Finance & Accounting Supervisor';
    case FinanceAccountingAdmin = 'Finance & Accounting Admin';

    // RQA
    case QualityAssuranceStaff = 'Quality Assurance Staff';
    case QualityControlStaff = 'Quality Control Staff';

    // Logistics
    case AdminLogistics = 'Admin Logistics';

    /**
     * Roles that see every record in the system.
     *
     * @return list<self>
     */
    public static function globalViewers(): array
    {
        return [self::SuperAdmin, self::ManagementDirector];
    }

    /**
     * Roles that manage a territory rather than a reporting line.
     *
     * @return list<self>
     */
    public static function territoryScoped(): array
    {
        return [self::RegionalSalesManager, self::AreaSalesManager];
    }

    /**
     * Roles that see only the records they own.
     *
     * @return list<self>
     */
    public static function ownRecordsOnly(): array
    {
        return [
            self::SalesRepresentative,
            self::MarketingStaff,
            self::ProductExecutive,
            self::JuniorProductManager,
            self::FinanceAccountingAdmin,
            self::QualityAssuranceStaff,
            self::QualityControlStaff,
            self::AdminLogistics,
        ];
    }

    /**
     * The names of the given cases, for hasRole()/hasAnyRole() calls.
     *
     * @param  list<self>  $roles
     * @return list<string>
     */
    public static function names(array $roles): array
    {
        return array_map(fn (self $role): string => $role->value, $roles);
    }
}
