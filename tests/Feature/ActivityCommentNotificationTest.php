<?php

use App\Filament\Resources\Activities\ActivityResource;
use App\Models\Activity;
use App\Models\ActivityComment;
use App\Models\Opportunity;
use App\Models\User;
use App\Notifications\ActivityCommentPosted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('notifies the activity owner when someone else comments', function () {
    Notification::fake();

    $rep = User::factory()->create();
    $author = User::factory()->create();
    $activity = Activity::factory()->create(['user_id' => $rep->id]);

    ActivityComment::factory()->create([
        'activity_id' => $activity->id,
        'user_id' => $author->id,
    ]);

    Notification::assertSentTo($rep, ActivityCommentPosted::class);
    Notification::assertNotSentTo($author, ActivityCommentPosted::class);
});

it('notifies attendees of the activity', function () {
    Notification::fake();

    $attendee = User::factory()->create();
    $author = User::factory()->create();
    $activity = Activity::factory()->create();
    $activity->attendees()->attach($attendee->id);

    ActivityComment::factory()->create([
        'activity_id' => $activity->id,
        'user_id' => $author->id,
    ]);

    Notification::assertSentTo($attendee, ActivityCommentPosted::class);
});

it('notifies the opportunity creator and collaborators', function () {
    Notification::fake();

    $creator = User::factory()->create();
    $collaborator = User::factory()->create();
    $author = User::factory()->create();

    $opportunity = Opportunity::factory()->create(['created_by' => $creator->id]);
    $opportunity->collaborators()->attach($collaborator->id, ['added_by' => $creator->id]);

    $activity = Activity::factory()->create([
        'lead_id' => null,
        'opportunity_id' => $opportunity->id,
    ]);

    ActivityComment::factory()->create([
        'activity_id' => $activity->id,
        'user_id' => $author->id,
    ]);

    Notification::assertSentTo($creator, ActivityCommentPosted::class);
    Notification::assertSentTo($collaborator, ActivityCommentPosted::class);
});

it('never notifies the comment author even when attached', function () {
    Notification::fake();

    $author = User::factory()->create();
    $activity = Activity::factory()->create(['user_id' => $author->id]);
    $activity->attendees()->attach($author->id);

    ActivityComment::factory()->create([
        'activity_id' => $activity->id,
        'user_id' => $author->id,
    ]);

    Notification::assertNotSentTo($author, ActivityCommentPosted::class);
});

it('notifies each recipient only once', function () {
    Notification::fake();

    $rep = User::factory()->create();
    $author = User::factory()->create();

    $opportunity = Opportunity::factory()->create(['created_by' => $rep->id]);
    $activity = Activity::factory()->create([
        'lead_id' => null,
        'opportunity_id' => $opportunity->id,
        'user_id' => $rep->id,
    ]);
    // Also an attendee and collaborator: same user via three paths.
    $activity->attendees()->attach($rep->id);
    $opportunity->collaborators()->attach($rep->id, ['added_by' => $rep->id]);

    ActivityComment::factory()->create([
        'activity_id' => $activity->id,
        'user_id' => $author->id,
    ]);

    Notification::assertSentToTimes($rep, ActivityCommentPosted::class, 1);
});

it('writes a Filament database notification record with a link to the activity', function () {
    $rep = User::factory()->create();
    $author = User::factory()->create();
    $activity = Activity::factory()->create(['user_id' => $rep->id]);

    ActivityComment::factory()->create([
        'activity_id' => $activity->id,
        'user_id' => $author->id,
    ]);

    $notification = $rep->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['format'])->toBe('filament')
        ->and($notification->data['title'])->toContain($activity->activity_code)
        ->and($notification->data['actions'][0]['url'])->toBe(
            ActivityResource::getUrl('view', ['record' => $activity])
        );
});

it('omits the activity link when the activity no longer exists', function () {
    $rep = User::factory()->create();
    $author = User::factory()->create();
    $activity = Activity::factory()->create(['user_id' => $rep->id]);
    $comment = ActivityComment::factory()->create([
        'activity_id' => $activity->id,
        'user_id' => $author->id,
    ]);

    $activity->delete();

    $data = (new ActivityCommentPosted($comment->refresh()))->toDatabase($rep);

    expect($data['actions'])->toBe([]);
});

it('sends the notification over the mail channel', function () {
    Notification::fake();

    $rep = User::factory()->create();
    $author = User::factory()->create();
    $activity = Activity::factory()->create(['user_id' => $rep->id]);

    ActivityComment::factory()->create([
        'activity_id' => $activity->id,
        'user_id' => $author->id,
    ]);

    Notification::assertSentTo(
        $rep,
        ActivityCommentPosted::class,
        fn (ActivityCommentPosted $notification) => in_array('mail', $notification->via($rep), true)
            && in_array('database', $notification->via($rep), true)
    );
});
