<?php

namespace Tests\Feature;

use App\Filament\Widgets\CustomerActivityStatsWidget;
use App\Filament\Widgets\CustomerCommentHistoryWidget;
use App\Filament\Widgets\CustomerRecentActivitiesWidget;
use App\Models\Activity;
use App\Models\ActivityComment;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('Super Admin');
    actingAs($this->user);
    $this->customer = Customer::factory()->create();
});

it('customer activity stats widget displays correct data', function () {
    Activity::factory()->count(3)->create([
        'customer_id' => $this->customer->id,
        'performed_at' => now(),
    ]);

    livewire(CustomerActivityStatsWidget::class, ['record' => $this->customer])
        ->assertSee('Total Customer Interactions');
});

it('customer recent activities widget displays only this customer data', function () {
    $activityThisCustomer = Activity::factory()->create([
        'customer_id' => $this->customer->id,
        'subject' => 'Correct Customer Activity',
    ]);

    $otherCustomer = Customer::factory()->create();
    $activityOtherCustomer = Activity::factory()->create([
        'customer_id' => $otherCustomer->id,
        'subject' => 'Wrong Customer Activity',
    ]);

    livewire(CustomerRecentActivitiesWidget::class, ['record' => $this->customer])
        ->assertCanSeeTableRecords([$activityThisCustomer])
        ->assertCanNotSeeTableRecords([$activityOtherCustomer]);
});

it('customer comment history widget shows only this customer comments in latest-first order', function () {
    $activity = Activity::factory()->create(['customer_id' => $this->customer->id]);
    $olderComment = ActivityComment::factory()->create([
        'activity_id' => $activity->id,
        'comment' => 'Older customer comment',
        'created_at' => now()->subDay(),
    ]);
    $newerComment = ActivityComment::factory()->create([
        'activity_id' => $activity->id,
        'comment' => 'Newest customer comment',
        'created_at' => now(),
    ]);

    $otherActivity = Activity::factory()->create();
    $otherComment = ActivityComment::factory()->create([
        'activity_id' => $otherActivity->id,
        'comment' => 'Other customer comment',
    ]);

    livewire(CustomerCommentHistoryWidget::class, ['record' => $this->customer])
        ->assertCanSeeTableRecords([$newerComment, $olderComment])
        ->assertCanNotSeeTableRecords([$otherComment])
        ->assertSeeInOrder(['Newest customer comment', 'Older customer comment']);
});

it('customer comment history widget can create a comment on a customer activity', function () {
    $activity = Activity::factory()->create(['customer_id' => $this->customer->id]);

    livewire(CustomerCommentHistoryWidget::class, ['record' => $this->customer])
        ->callTableAction('create', data: [
            'activity_id' => $activity->id,
            'comment' => 'A new customer comment',
        ])
        ->assertHasNoTableActionErrors();

    expect(ActivityComment::query()->where('activity_id', $activity->id)->where('comment', 'A new customer comment')->exists())->toBeTrue();
});

it('customer recent activities widget exposes a working view action', function () {
    Activity::factory()->create([
        'customer_id' => $this->customer->id,
        'subject' => 'Viewable Customer Activity',
    ]);

    livewire(CustomerRecentActivitiesWidget::class, ['record' => $this->customer])
        ->assertSuccessful()
        ->assertSee('View');
});
