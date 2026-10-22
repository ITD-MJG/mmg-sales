<?php

namespace Tests\Feature;

use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    actingAs($admin);
});

it('shows first attendee and count in Assigned To with remaining names in the tooltip', function () {
    $creator = User::factory()->create();
    $firstAttendee = User::factory()->create(['name' => 'Attendee One']);
    $secondAttendee = User::factory()->create(['name' => 'Attendee Two']);
    $thirdAttendee = User::factory()->create(['name' => 'Attendee Three']);

    $customer = Customer::factory()->create();
    $lead = Lead::query()->create([
        'title' => 'A lead',
        'customer_name' => $customer->name,
        'customer_id' => $customer->id,
        'phone' => '000',
        'created_by' => $creator->id,
    ]);
    $activity = Activity::factory()->forLead($lead)->create(['user_id' => $creator->id]);
    $activity->attendees()->attach([$firstAttendee->id, $secondAttendee->id, $thirdAttendee->id]);

    Livewire::test(ListActivities::class)
        ->assertSee('Attendee One + 2')
        ->assertSee('Attendee Two, Attendee Three');
});
