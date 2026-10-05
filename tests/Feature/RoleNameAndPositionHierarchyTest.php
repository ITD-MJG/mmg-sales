<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Build the Sales ladder as it exists in production.
 *
 * The seeder only knows three Sales positions (RSM, ASM, Representative) and
 * already links them by level. Production additionally carries the two
 * supervisor positions, unlinked, which is the state these tests reproduce.
 */
function seedSalesLadder(): array
{
    $dept = Department::factory()->create(['name' => 'Sales']);

    $regional = Position::factory()->create([
        'name' => 'Regional Sales Manager', 'level' => 2, 'department_id' => $dept->id,
    ]);
    $area = Position::factory()->create([
        'name' => 'Area Sales Manager', 'level' => 3, 'parent_id' => $regional->id,
        'department_id' => $dept->id,
    ]);
    $clinical = Position::factory()->create([
        'name' => 'Sales Supervisor Clinical Diagnostic', 'level' => 4,
        'department_id' => $dept->id,
    ]);
    $lifeScience = Position::factory()->create([
        'name' => 'Sales Supervisor Life Science', 'level' => 4,
        'department_id' => $dept->id,
    ]);
    $representative = Position::factory()->create([
        'name' => 'Sales Representative', 'level' => 5, 'department_id' => $dept->id,
    ]);

    return compact('regional', 'area', 'clinical', 'lifeScience', 'representative');
}

function runPositionLinkMigration(): void
{
    (require database_path('migrations/2026_10_05_000002_link_sales_position_hierarchy.php'))->up();
}

it('links both sales supervisor positions under an area sales manager', function () {
    $ladder = seedSalesLadder();

    runPositionLinkMigration();

    expect($ladder['clinical']->fresh()->parent_id)->toBe($ladder['area']->id)
        ->and($ladder['lifeScience']->fresh()->parent_id)->toBe($ladder['area']->id);
});

it('makes a regional sales manager resolve the supervisors as descendants', function () {
    $ladder = seedSalesLadder();

    runPositionLinkMigration();

    $descendants = $ladder['regional']->fresh()->getAllDescendantIds();

    expect($descendants)->toContain($ladder['area']->id)
        ->and($descendants)->toContain($ladder['clinical']->id)
        ->and($descendants)->toContain($ladder['lifeScience']->id);
});

it('leaves the sales representative position unlinked because two supervisors exist', function () {
    $ladder = seedSalesLadder();

    runPositionLinkMigration();

    expect($ladder['representative']->fresh()->parent_id)->toBeNull();
});

it('does not overwrite a supervisor link an operator already set', function () {
    $ladder = seedSalesLadder();

    // An operator has pointed the clinical supervisor at the regional manager
    // directly. The migration must respect that.
    $ladder['clinical']->update(['parent_id' => $ladder['regional']->id]);

    runPositionLinkMigration();

    expect($ladder['clinical']->fresh()->parent_id)->toBe($ladder['regional']->id);
});

it('never creates a cycle when linking', function () {
    $ladder = seedSalesLadder();

    // Area manager now sits under the supervisor it would otherwise parent.
    $ladder['area']->update(['parent_id' => $ladder['clinical']->id]);

    runPositionLinkMigration();

    // The link must be refused rather than producing a loop.
    expect($ladder['clinical']->fresh()->parent_id)->not->toBe($ladder['area']->id);
});

it('names every sales role after its position, with no department in the name', function () {
    seedSalesLadder();

    foreach ([
        Role::RegionalSalesManager,
        Role::AreaSalesManager,
        Role::SalesSupervisorClinicalDiagnostic,
        Role::SalesSupervisorLifeScience,
        Role::SalesRepresentative,
    ] as $role) {
        expect(Position::where('name', $role->value)->exists())->toBeTrue(
            "Role {$role->name} ('{$role->value}') has no matching position"
        );

        // The department belongs in the role's department_id column, not its name,
        // and never in the removed "{Position} - {Department}" shape.
        expect($role->value)->not->toContain(' - ');
    }
});

it('renames a drifted position-department role onto the bare position name', function () {
    seedSalesLadder();

    // Reproduces the production shape: the row is named in the old
    // "{Position} - {Department}" format while the code checks the bare name.
    $drifted = App\Models\Role::create(['name' => 'Area Sales Manager - Sales', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($drifted);

    expect($user->hasRole(Role::AreaSalesManager->value))->toBeFalse();

    (require database_path('migrations/2026_10_05_000001_normalize_role_names_to_position.php'))->up();

    expect(App\Models\Role::where('name', 'Area Sales Manager - Sales')->exists())->toBeFalse()
        ->and($user->fresh()->hasRole(Role::AreaSalesManager->value))->toBeTrue();
});

it('merges a drifted role into an existing canonical role without losing users', function () {
    seedSalesLadder();

    $canonical = App\Models\Role::create(['name' => 'Area Sales Manager', 'guard_name' => 'web']);
    $drifted = App\Models\Role::create(['name' => 'Area Sales Manager - Sales', 'guard_name' => 'web']);

    $a = User::factory()->create();
    $b = User::factory()->create();
    $a->assignRole($canonical);
    $b->assignRole($drifted);

    (require database_path('migrations/2026_10_05_000001_normalize_role_names_to_position.php'))->up();

    expect(App\Models\Role::where('name', 'Area Sales Manager')->count())->toBe(1)
        ->and($a->fresh()->hasRole('Area Sales Manager'))->toBeTrue()
        ->and($b->fresh()->hasRole('Area Sales Manager'))->toBeTrue();
});
