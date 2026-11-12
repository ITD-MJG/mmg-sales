<?php

use App\DTOs\ReportFilterData;
use App\DTOs\SalesStatisticsData;
use App\DTOs\TargetAchievementData;
use App\Filament\Resources\Reports\Pages\SalesStatisticsPage;
use App\Filament\Resources\Reports\SalesStatisticsResource;
use App\Models\Activity;
use App\Models\Department;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Order;
use App\Models\User;
use App\Services\Reports\SalesStatisticsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seed(RolesAndPermissionsSeeder::class);
    Cache::flush();

    $this->sales = Department::create(['name' => Department::SALES]);
    $this->marketing = Department::create(['name' => Department::MARKETING]);

    $this->manager = User::factory()->create([
        'name' => 'Manager One',
        'department_id' => $this->sales->id,
    ]);
    $this->rep = User::factory()->create([
        'name' => 'Rep One',
        'department_id' => $this->sales->id,
        'manager_id' => $this->manager->id,
    ]);

});

function statisticsFor(?int $userId, ?int $managerId = null)
{
    return app(SalesStatisticsService::class)->generate(
        ReportFilterData::fromArray([
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'user_id' => $userId,
        ]),
        $managerId,
    );
}

it('reports a single rep when a sales rep is selected', function (): void {
    Order::factory()->create([
        'sales_id' => $this->rep->id,
        'order_date' => now()->toDateString(),
        'total_amount' => 1_000_000,
    ]);
    Lead::factory()->create([
        'created_by' => $this->rep->id,
        'created_at' => now(),
        'estimated_revenue' => 500_000,
    ]);

    $data = statisticsFor($this->rep->id);

    expect($data->totalOrders)->toBe(1)
        ->and($data->totalRevenue)->toBe(1_000_000.0)
        ->and($data->totalLeads)->toBe(1)
        ->and($data->totalLeadValue)->toBe(500_000.0)
        ->and($data->averageOrderValue)->toBe(1_000_000.0)
        ->and($data->managerName)->toBeNull();
});

it('credits the assigned rep, not whoever keyed the order', function (): void {
    $admin = User::factory()->create(['department_id' => $this->sales->id]);

    Order::factory()->create([
        'sales_id' => $this->rep->id,
        'created_by' => $admin->id,
        'order_date' => now()->toDateString(),
        'total_amount' => 750_000,
    ]);

    expect(statisticsFor($this->rep->id)->totalRevenue)->toBe(750_000.0)
        ->and(statisticsFor($admin->id)->totalRevenue)->toBe(0.0);
});

it('rolls a manager line up from the whole reporting chain', function (): void {
    $subordinate = User::factory()->create([
        'department_id' => $this->sales->id,
        'manager_id' => $this->rep->id,
    ]);

    foreach ([$this->manager->id, $this->rep->id, $subordinate->id] as $userId) {
        Order::factory()->create([
            'sales_id' => $userId,
            'order_date' => now()->toDateString(),
            'total_amount' => 100_000,
        ]);
    }

    $data = statisticsFor(null, $this->manager->id);

    expect($data->totalOrders)->toBe(3)
        ->and($data->totalRevenue)->toBe(300_000.0)
        ->and($data->managerName)->toBe('Manager One');
});

it('excludes users outside the selected manager chain', function (): void {
    $outsider = User::factory()->create(['department_id' => $this->sales->id]);

    Order::factory()->create([
        'sales_id' => $this->rep->id,
        'order_date' => now()->toDateString(),
        'total_amount' => 100_000,
    ]);
    Order::factory()->create([
        'sales_id' => $outsider->id,
        'order_date' => now()->toDateString(),
        'total_amount' => 900_000,
    ]);

    $data = statisticsFor(null, $this->manager->id);

    expect($data->totalRevenue)->toBe(100_000.0);
});

it('lists only the manager reporting line in the team breakdown', function (): void {
    $data = statisticsFor(null, $this->manager->id);

    expect($data->teamRows->pluck('name')->all())->toBe(['Rep One'])
        ->and($data->teamSize)->toBe(1);
});

it('ignores records outside the selected date range', function (): void {
    Order::factory()->create([
        'sales_id' => $this->rep->id,
        'order_date' => now()->subYear()->toDateString(),
        'total_amount' => 5_000_000,
    ]);

    $data = statisticsFor($this->rep->id);

    expect($data->totalOrders)->toBe(0)
        ->and($data->totalRevenue)->toBe(0.0);
});

it('counts activities performed by the selected rep', function (): void {
    // Attached to an opportunity rather than a lead: an activity on a lead is
    // advanced by ActivityObserver, so tying this test to a lead would couple it
    // to the lead status vocabulary. Only the attribution is under test.
    $opportunity = Opportunity::factory()->create(['created_by' => $this->rep->id]);

    Activity::factory()->create([
        'user_id' => $this->rep->id,
        'lead_id' => null,
        'opportunity_id' => $opportunity->id,
        'performed_at' => now(),
    ]);

    expect(statisticsFor($this->rep->id)->totalActivities)->toBe(1);
});

it('returns an empty result when nothing is selected', function (): void {
    $data = statisticsFor(null);

    expect($data->totalOrders)->toBe(0)
        ->and($data->teamRows)->toBeEmpty()
        ->and($data->monthlyTrend)->toBeEmpty();
});

it('only offers Sales department users in the page selects', function (): void {
    User::factory()->create([
        'name' => 'Marketing Person',
        'department_id' => $this->marketing->id,
    ]);

    $options = SalesStatisticsPage::salesDepartmentOptions();

    expect($options)->toHaveKey($this->rep->id)
        ->and($options)->toHaveKey($this->manager->id)
        ->and($options)->not->toContain('Marketing Person');
});

it('renders the sales statistics page for a user with report access', function (): void {
    $this->manager->assignRole('Sales Manager');

    actingAs($this->manager);

    Livewire::test(SalesStatisticsPage::class)
        ->assertSuccessful()
        ->assertSee('Sales Rep')
        ->assertSee('Manager');
});

it('places the report under the CRM navigation group', function (): void {
    expect(SalesStatisticsResource::getNavigationGroup())->toBe('CRM')
        ->and(SalesStatisticsResource::getNavigationLabel())->toBe('Sales Statistics')
        ->and(SalesStatisticsResource::getSlug())->toBe('crm/sales-statistics');
});

it('hides the page from users without the statistics permission', function (): void {
    $user = User::factory()->create(['department_id' => $this->marketing->id]);

    expect($user->can('view_sales_statistics'))->toBeFalse();

    actingAs($user);

    expect(SalesStatisticsResource::canAccess())->toBeFalse();
});

it('round-trips its DTO through the cache store', function (): void {
    // The cache store only unserializes allow-listed classes (config/cache.php
    // `serializable_classes`). A DTO missing from that list comes back as
    // __PHP_Incomplete_Class and blows up the widget with a TypeError.
    $data = statisticsFor($this->rep->id);

    Cache::put('sales_statistics_round_trip', $data, now()->addMinutes(5));

    expect(Cache::get('sales_statistics_round_trip'))
        ->toBeInstanceOf(SalesStatisticsData::class);
});

it('allow-lists every DTO the reports cache', function (): void {
    $allowed = config('cache.serializable_classes');

    expect($allowed)->toContain(SalesStatisticsData::class)
        ->and($allowed)->toContain(TargetAchievementData::class);
});
