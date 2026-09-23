<?php

namespace Tests\Unit;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\Department;
use App\Models\User;
use App\Services\ActivityScopeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityScopeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ActivityScopeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->service = new ActivityScopeService;
    }

    public function test_sales_representative_can_only_see_their_own_activities()
    {
        $sr = User::factory()->create();
        $sr->assignRole('Sales Staff');
        $otherUser = User::factory()->create();

        $srActivity = Activity::factory()->create(['user_id' => $sr->id]);
        $otherActivity = Activity::factory()->create(['user_id' => $otherUser->id]);

        $results = $this->service->getActivityQuery($sr)->get();

        $this->assertTrue($results->contains($srActivity));
        $this->assertFalse($results->contains($otherActivity));
    }

    public function test_manager_can_see_their_own_and_subordinates_activities()
    {
        $manager = User::factory()->create();
        $manager->assignRole('Area Sales Manager');

        $subordinate = User::factory()->create(['manager_id' => $manager->id]);
        $grandSubordinate = User::factory()->create(['manager_id' => $subordinate->id]);
        $otherUser = User::factory()->create();

        $managerActivity = Activity::factory()->create(['user_id' => $manager->id]);
        $subActivity = Activity::factory()->create(['user_id' => $subordinate->id]);
        $grandSubActivity = Activity::factory()->create(['user_id' => $grandSubordinate->id]);
        $otherActivity = Activity::factory()->create(['user_id' => $otherUser->id]);

        $results = $this->service->getActivityQuery($manager)->get();

        $this->assertTrue($results->contains($managerActivity));
        $this->assertTrue($results->contains($subActivity));
        $this->assertTrue($results->contains($grandSubActivity));
        $this->assertFalse($results->contains($otherActivity));
    }

    public function test_head_can_see_all_activities()
    {
        // A global viewer: Management-department Director sees every activity.
        $management = Department::factory()->create(['name' => Department::MANAGEMENT]);
        $head = User::factory()->create(['department_id' => $management->id]);
        $head->assignRole('Management Director');
        $sr = User::factory()->create();

        $activity = Activity::factory()->create(['user_id' => $sr->id]);

        $results = $this->service->getActivityQuery($head)->get();

        $this->assertTrue($results->contains($activity));
    }

    public function test_can_calculate_activity_stats()
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        Activity::factory()->count(3)->create([
            'user_id' => $user->id,
            'performed_at' => now(),
        ]);

        Activity::factory()->count(2)->create([
            'user_id' => $user->id,
            'performed_at' => now()->subMonth(),
        ]);

        $stats = $this->service->getActivityStats($user);

        $this->assertEquals(5, $stats['total']);
        $this->assertEquals(3, $stats['monthly']);
        $this->assertEquals(50, $stats['growth']); // (3-2)/2 * 100
    }

    public function test_can_get_customer_activity_stats()
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        $customer = Customer::factory()->create();

        $lastMonth = now()->subMonth();
        $today = now();

        Activity::factory()->create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'performed_at' => $lastMonth,
        ]);

        Activity::factory()->create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'performed_at' => $today,
        ]);

        $stats = $this->service->getCustomerActivityStats($user, $customer->id);

        $this->assertEquals(2, $stats['total']);
        $this->assertEquals($today->toDateTimeString(), $stats['last_activity_date']->toDateTimeString());
    }
}
