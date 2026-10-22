<?php

use App\Filament\Resources\Targets\TargetResource;
use App\Models\Position;
use App\Models\Target;
use App\Models\Territory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
});

function visibleTargetIds(User $user): array
{
    Auth::login($user);

    $query = Target::query();
    TargetResource::applyVisibilityScope($query, 'user_id');
    $ids = $query->pluck('id')->all();

    Auth::logout();

    return $ids;
}

it('shows own and reporting-line targets but hides unrelated targets', function () {
    $territory = Territory::factory()->create();
    $managerPosition = Position::factory()->create(['name' => 'Sales Manager']);
    $repPosition = Position::factory()->create(['name' => 'Sales Representative', 'parent_id' => $managerPosition->id]);

    $manager = User::factory()->create(['position_id' => $managerPosition->id, 'territory_id' => $territory->id]);
    $manager->assignRole('Sales Manager');
    $report = User::factory()->create(['manager_id' => $manager->id, 'position_id' => $repPosition->id, 'territory_id' => $territory->id]);
    $report->assignRole('Sales Representative');
    $other = User::factory()->create();
    $other->assignRole('Sales Representative');

    $own = Target::query()->create(['user_id' => $manager->id, 'year' => 2026]);
    $reportTarget = Target::query()->create(['user_id' => $report->id, 'year' => 2026]);
    $otherTarget = Target::query()->create(['user_id' => $other->id, 'year' => 2026]);

    $ids = visibleTargetIds($manager);

    expect($ids)->toContain($own->id, $reportTarget->id)
        ->not->toContain($otherTarget->id);
});

it('shows every target to a super admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $owner = User::factory()->create();
    $target = Target::query()->create(['user_id' => $owner->id, 'year' => 2026]);

    expect(visibleTargetIds($admin))->toContain($target->id);
});
