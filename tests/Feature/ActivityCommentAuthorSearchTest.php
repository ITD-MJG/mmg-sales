<?php

namespace Tests\Feature;

use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Models\Activity;
use App\Models\Lead;
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
});

it('finds activities by the name of a comment author', function () {
    $author = User::factory()->create(['name' => 'Zulkifli Harahap']);

    $commented = Activity::factory()->create();
    $commented->comments()->create(['user_id' => $author->id, 'comment' => 'Follow up next week.']);

    $other = Activity::factory()->create();

    livewire(ListActivities::class)
        ->searchTable('Zulkifli')
        ->assertCanSeeTableRecords([$commented])
        ->assertCanNotSeeTableRecords([$other]);
});

it('finds leads by the name of a comment author on their activities', function () {
    $author = User::factory()->create(['name' => 'Zulkifli Harahap']);

    $lead = Lead::factory()->create(['created_by' => $this->user->id]);
    $activity = Activity::factory()->forLead($lead)->create();
    $activity->comments()->create(['user_id' => $author->id, 'comment' => 'Budget confirmed.']);

    $other = Lead::factory()->create(['created_by' => $this->user->id]);

    livewire(ListLeads::class)
        ->searchTable('Zulkifli')
        ->assertCanSeeTableRecords([$lead])
        ->assertCanNotSeeTableRecords([$other]);
});

it('matches a record that carries comments from several authors', function () {
    $first = User::factory()->create(['name' => 'Zulkifli Harahap']);
    $second = User::factory()->create(['name' => 'Nadia Pratiwi']);

    $activity = Activity::factory()->create();
    $activity->comments()->create(['user_id' => $first->id, 'comment' => 'Budget confirmed.']);
    $activity->comments()->create(['user_id' => $second->id, 'comment' => 'Need approval.']);

    livewire(ListActivities::class)
        ->searchTable('Nadia')
        ->assertCanSeeTableRecords([$activity]);

    livewire(ListActivities::class)
        ->searchTable('Zulkifli')
        ->assertCanSeeTableRecords([$activity]);
});

it('does not match activities or leads on unrelated comment text', function () {
    $author = User::factory()->create(['name' => 'Zulkifli Harahap']);

    $activity = Activity::factory()->create();
    $activity->comments()->create(['user_id' => $author->id, 'comment' => 'Budget confirmed.']);

    $lead = Lead::factory()->create(['created_by' => $this->user->id]);
    $leadActivity = Activity::factory()->forLead($lead)->create();
    $leadActivity->comments()->create(['user_id' => $author->id, 'comment' => 'Budget confirmed.']);

    livewire(ListActivities::class)
        ->searchTable('Budget confirmed')
        ->assertCanNotSeeTableRecords([$activity]);

    livewire(ListLeads::class)
        ->searchTable('Budget confirmed')
        ->assertCanNotSeeTableRecords([$lead]);
});
