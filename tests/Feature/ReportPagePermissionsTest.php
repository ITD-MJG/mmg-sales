<?php

use App\Filament\Resources\Reports\CustomerReportResource;
use App\Filament\Resources\Reports\PipelineReportResource;
use App\Filament\Resources\Reports\ProductReportResource;
use App\Filament\Resources\Reports\SalesReportResource;
use App\Filament\Resources\Reports\TargetAchievementResource;
use App\Filament\Resources\Reports\TargetReportResource;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seed(RolesAndPermissionsSeeder::class);

    $this->management = Department::factory()->create(['name' => Department::MANAGEMENT]);
    $this->sales = Department::factory()->create(['name' => Department::SALES]);
});

function reportResources(): array
{
    return [
        SalesReportResource::class => 'view_report_sales',
        ProductReportResource::class => 'view_report_products',
        CustomerReportResource::class => 'view_report_customers',
        PipelineReportResource::class => 'view_report_leads',
        TargetReportResource::class => 'view_report_targets',
        TargetAchievementResource::class => 'view_report_target_achievement',
    ];
}

it('gates every Reports page on its own permission', function (): void {
    expect(reportResources())->toHaveCount(6);

    foreach (reportResources() as $resource => $permission) {
        expect($permission)->not->toBe('view_sales_reports');

        actingAs(User::factory()->create());
        expect($resource::canAccess())->toBeFalse();

        $user = User::factory()->create();
        $user->givePermissionTo($permission);
        actingAs($user);

        expect($resource::canAccess())->toBeTrue();
    }
});

it('grants every report page permission to the Management department', function (): void {
    $director = User::factory()->create(['department_id' => $this->management->id]);
    $director->assignRole('Management Director');

    foreach (reportResources() as $resource => $permission) {
        expect($director->can($permission))->toBeTrue();

        actingAs($director);
        expect($resource::canAccess())->toBeTrue();
    }
});

it('keeps report pages closed to the sales roles that see the dashboards', function (): void {
    foreach (['Sales Manager', 'Regional Sales Manager', 'Area Sales Manager', 'Marketing Manager'] as $roleName) {
        $user = User::factory()->create(['department_id' => $this->sales->id]);
        $user->assignRole($roleName);

        // The dashboard permission is still held, the page permission is not.
        expect($user->can('view_sales_reports'))->toBeTrue();

        actingAs($user);

        foreach (reportResources() as $resource => $permission) {
            expect($resource::canAccess())->toBeFalse();
        }
    }
});

it('lets a Super Admin through without holding the permissions', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('Super Admin');

    actingAs($superAdmin);

    foreach (array_keys(reportResources()) as $resource) {
        expect($resource::canAccess())->toBeTrue();
    }
});
