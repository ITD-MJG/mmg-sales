<?php

use App\Filament\Widgets\LeadStatusChart;
use App\Models\Department;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

use function Pest\Laravel\actingAs;
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
function leadStatusCounts(User $user): array
{
    Auth::login($user);

    $widget = new LeadStatusChart;
    $method = new ReflectionMethod($widget, 'getData');
    $method->setAccessible(true);

    $data = $method->invoke($widget);

    Auth::logout();

    return $data['datasets'][0]['data'];
}

it('counts every lead for a Management Director in the Lead Status chart', function () {
    $director = managementDirector();

    $otherRep = User::factory()->create();
    $otherRep->assignRole('Sales Staff');

    Lead::factory()->count(3)->create(['status' => 'new', 'created_by' => $otherRep->id]);
    Lead::factory()->count(2)->create(['status' => 'won', 'created_by' => $otherRep->id]);
    Lead::factory()->count(4)->create(['status' => 'lost', 'created_by' => $otherRep->id]);

    $counts = leadStatusCounts($director);

    expect(array_sum($counts))->toBe(Lead::count())
        ->and(array_sum($counts))->toBe(9);
});

it('still counts only own leads for a Sales Staff user in the Lead Status chart', function () {
    $rep = User::factory()->create();
    $rep->assignRole('Sales Staff');

    $other = User::factory()->create();
    $other->assignRole('Sales Staff');

    Lead::factory()->count(2)->create(['status' => 'new', 'created_by' => $rep->id]);
    Lead::factory()->count(5)->create(['status' => 'new', 'created_by' => $other->id]);

    $counts = leadStatusCounts($rep);

    expect(array_sum($counts))->toBe(2);
});

it('counts every lead for a Super Admin in the Lead Status chart', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $other = User::factory()->create();
    $other->assignRole('Sales Staff');

    Lead::factory()->count(6)->create(['status' => 'qualified', 'created_by' => $other->id]);

    $counts = leadStatusCounts($admin);

    expect(array_sum($counts))->toBe(6);
});
