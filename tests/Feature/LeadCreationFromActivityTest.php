<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
});

it('auto-links unlinked activities when lead is created', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Admin');
    actingAs($user);

    $customer = Customer::factory()->create(['type' => 'hospital_clinic']);

    // Create 2 activities without a lead
    $activity1 = Activity::factory()->create([
        'customer_id' => $customer->id,
        'user_id' => $user->id,
        'lead_id' => null,
        'performed_at' => now(),
    ]);
    $activity2 = Activity::factory()->create([
        'customer_id' => $customer->id,
        'user_id' => $user->id,
        'lead_id' => null,
        'performed_at' => now(),
    ]);

    // Create a lead for the same customer (via factory, then run auto-link logic)
    $lead = Lead::factory()->create([
        'customer_id' => $customer->id,
        'created_by' => $user->id,
    ]);

    // Simulate the afterCreate auto-link logic
    Activity::where('customer_id', $lead->customer_id)
        ->whereNull('lead_id')
        ->update(['lead_id' => $lead->id]);

    // Both activities should now be linked to the lead
    assertDatabaseHas('activities', [
        'id' => $activity1->id,
        'lead_id' => $lead->id,
    ]);
    assertDatabaseHas('activities', [
        'id' => $activity2->id,
        'lead_id' => $lead->id,
    ]);
});

it('does not relink activities already assigned to another lead', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Admin');
    actingAs($user);

    $customer = Customer::factory()->create(['type' => 'hospital_clinic']);

    // Create a lead first
    $existingLead = Lead::factory()->create([
        'customer_id' => $customer->id,
    ]);

    // Activity linked to existing lead
    $activityLinked = Activity::factory()->create([
        'customer_id' => $customer->id,
        'user_id' => $user->id,
        'lead_id' => $existingLead->id,
        'performed_at' => now(),
    ]);

    // Create a second lead for the same customer
    $newLead = Lead::factory()->create([
        'customer_id' => $customer->id,
        'created_by' => $user->id,
    ]);

    // Simulate the afterCreate auto-link logic
    Activity::where('customer_id', $newLead->customer_id)
        ->whereNull('lead_id')
        ->update(['lead_id' => $newLead->id]);

    // Activity should still be linked to the original lead, not the new one
    assertDatabaseHas('activities', [
        'id' => $activityLinked->id,
        'lead_id' => $existingLead->id,
    ]);
    assertDatabaseMissing('activities', [
        'id' => $activityLinked->id,
        'lead_id' => $newLead->id,
    ]);
});
