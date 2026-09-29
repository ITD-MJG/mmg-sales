<?php

use App\Filament\Widgets\OpportunityStatusChart;
use App\Models\Department;
use App\Models\Opportunity;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
});

/** A Director sitting in the Management department: global read-only visibility. */
function managementDirector(): User
{
    $department = Department::factory()->create(['name' => Department::MANAGEMENT]);

    return tap(User::factory()->create(['department_id' => $department->id]), function (User $user): void {
        $user->assignRole('Management Director');
    });
}

/** Read the chart's dataset, which is what the dashboard renders. */
function opportunityStatusCounts(User $user): array
{
    Auth::login($user);

    $widget = new OpportunityStatusChart;
    $method = new ReflectionMethod($widget, 'getData');
    $method->setAccessible(true);

    $data = $method->invoke($widget);

    Auth::logout();

    return $data['datasets'][0]['data'];
}

it('counts every opportunity for a Management Director in the Opportunity Status chart', function () {
    $director = managementDirector();

    $otherRep = User::factory()->create();
    $otherRep->assignRole('Sales Staff');

    Opportunity::factory()->count(3)->create(['stage' => 'new', 'created_by' => $otherRep->id]);
    Opportunity::factory()->count(2)->create(['stage' => 'won', 'created_by' => $otherRep->id]);
    Opportunity::factory()->count(4)->create(['stage' => 'lost', 'created_by' => $otherRep->id]);

    $counts = opportunityStatusCounts($director);

    expect(array_sum($counts))->toBe(Opportunity::count())
        ->and(array_sum($counts))->toBe(9);
});

it('still counts only own opportunities for a Sales Staff user in the Opportunity Status chart', function () {
    $rep = User::factory()->create();
    $rep->assignRole('Sales Staff');

    $other = User::factory()->create();
    $other->assignRole('Sales Staff');

    Opportunity::factory()->count(2)->create(['stage' => 'new', 'created_by' => $rep->id]);
    Opportunity::factory()->count(5)->create(['stage' => 'new', 'created_by' => $other->id]);

    $counts = opportunityStatusCounts($rep);

    expect(array_sum($counts))->toBe(2);
});

it('counts every opportunity for a Super Admin in the Opportunity Status chart', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $other = User::factory()->create();
    $other->assignRole('Sales Staff');

    Opportunity::factory()->count(6)->create(['stage' => 'qualified', 'created_by' => $other->id]);

    $counts = opportunityStatusCounts($admin);

    expect(array_sum($counts))->toBe(6);
});
