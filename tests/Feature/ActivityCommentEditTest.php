<?php

namespace Tests\Feature;

use App\Filament\Resources\Leads\Pages\ViewLead;
use App\Filament\Resources\Leads\RelationManagers\ActivityCommentsRelationManager as LeadActivityComments;
use App\Filament\Resources\Opportunities\Pages\ViewOpportunity;
use App\Filament\Resources\Opportunities\RelationManagers\ActivityCommentsRelationManager as OpportunityActivityComments;
use App\Models\Activity;
use App\Models\ActivityComment;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

/**
 * Editing a comment must not re-validate which activity it hangs off.
 *
 * The activity is chosen once, when the comment is created, and authorised
 * against the create rules at that moment. On edit the form used to submit the
 * stored `activity_id` back through the same "you can only comment on
 * activities you belong to" rule, so a comment written by someone who is not
 * attached to the activity failed validation on a field the user never touched.
 */
beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
    Role::findOrCreate('Sales Staff');

    $this->author = User::factory()->create(['name' => 'Comment Author']);
    $this->author->assignRole('Sales Staff');

    $this->owner = User::factory()->create(['name' => 'Activity Owner']);
    $this->owner->assignRole('Sales Staff');
});

/**
 * A comment the author wrote on an activity they are no longer attached to:
 * logged by someone else, on a lead the author did not create, with no
 * attendee row.
 */
function detachedComment(User $author, User $owner): ActivityComment
{
    $lead = Lead::factory()->create(['created_by' => $owner->id]);
    $activity = Activity::factory()->forLead($lead)->create(['user_id' => $owner->id]);

    return ActivityComment::factory()->create([
        'activity_id' => $activity->id,
        'user_id' => $author->id,
        'comment' => 'Original text',
    ]);
}

it('lets the author edit a comment on an activity they are no longer attached to', function () {
    $comment = detachedComment($this->author, $this->owner);
    actingAs($this->author);

    livewire(LeadActivityComments::class, [
        'ownerRecord' => $comment->activity->lead,
        'pageClass' => ViewLead::class,
    ])
        ->callTableAction('edit', $comment, data: ['comment' => 'Reworded'])
        ->assertHasNoActionErrors();

    expect($comment->fresh()->comment)->toBe('Reworded');
});

it('does not move a comment to another activity through the edit form', function () {
    $comment = detachedComment($this->author, $this->owner);
    $otherActivity = Activity::factory()->forLead($comment->activity->lead)->create(['user_id' => $this->author->id]);

    actingAs($this->author);

    livewire(LeadActivityComments::class, [
        'ownerRecord' => $comment->activity->lead,
        'pageClass' => ViewLead::class,
    ])
        ->callTableAction('edit', $comment, data: [
            'activity_id' => $otherActivity->id,
            'comment' => 'Reworded',
        ])
        ->assertHasNoActionErrors();

    expect($comment->fresh())
        ->comment->toBe('Reworded')
        ->activity_id->toBe($comment->activity_id);
});

it('still refuses to attach a new comment to an activity the user is not part of', function () {
    $lead = Lead::factory()->create(['created_by' => $this->author->id]);
    $foreignActivity = Activity::factory()->forLead(Lead::factory()->create(['created_by' => $this->owner->id]))
        ->create(['user_id' => $this->owner->id]);

    actingAs($this->author);

    livewire(LeadActivityComments::class, [
        'ownerRecord' => $lead,
        'pageClass' => ViewLead::class,
    ])
        ->callTableAction('create', data: [
            'activity_id' => $foreignActivity->id,
            'comment' => 'Sneaking in',
        ])
        ->assertHasActionErrors(['activity_id']);

    expect(ActivityComment::query()->count())->toBe(0);
});

it('applies the same rule to the opportunity activity comments', function () {
    $opportunity = Opportunity::factory()->create(['created_by' => $this->owner->id]);

    $activity = Activity::factory()->create([
        'user_id' => $this->owner->id,
        'lead_id' => null,
        'opportunity_id' => $opportunity->id,
    ]);

    $comment = ActivityComment::factory()->create([
        'activity_id' => $activity->id,
        'user_id' => $this->author->id,
        'comment' => 'Original text',
    ]);

    actingAs($this->author);

    livewire(OpportunityActivityComments::class, [
        'ownerRecord' => $opportunity,
        'pageClass' => ViewOpportunity::class,
    ])
        ->callTableAction('edit', $comment, data: ['comment' => 'Reworded'])
        ->assertHasNoActionErrors();

    expect($comment->fresh()->comment)->toBe('Reworded');
});
