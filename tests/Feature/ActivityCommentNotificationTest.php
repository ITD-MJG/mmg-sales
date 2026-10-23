<?php

use App\Filament\Resources\Activities\ActivityResource;
use App\Models\Activity;
use App\Models\ActivityComment;
use App\Models\Opportunity;
use App\Models\User;
use App\Notifications\ActivityCommentEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('sends official Filament database notifications to activity recipients', function () {
    $rep = User::factory()->create();
    $author = User::factory()->create();
    $activity = Activity::factory()->create(['user_id' => $rep->id]);
    $comment = ActivityComment::factory()->create([
        'activity_id' => $activity->id,
        'user_id' => $author->id,
    ]);

    $notification = $rep->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['format'])->toBe('filament')
        ->and($notification->data['title'])->toContain($activity->activity_code)
        ->and($notification->data['activity_id'])->toBe($activity->id)
        ->and($notification->data['comment_id'])->toBe($comment->id)
        ->and($notification->data['actions'][0]['url'])->toBe(
            ActivityResource::getUrl('view', ['record' => $activity])
        )
        ->and($notification->data['actions'][0]['label'])->toBe('View Activity');
});

it('keeps notifying attendees and opportunity participants', function () {
    $creator = User::factory()->create();
    $collaborator = User::factory()->create();
    $attendee = User::factory()->create();
    $author = User::factory()->create();
    $opportunity = Opportunity::factory()->create(['created_by' => $creator->id]);
    $opportunity->collaborators()->attach($collaborator->id, ['added_by' => $creator->id]);
    $activity = Activity::factory()->create([
        'lead_id' => null,
        'opportunity_id' => $opportunity->id,
    ]);
    $activity->attendees()->attach($attendee->id);

    ActivityComment::factory()->create([
        'activity_id' => $activity->id,
        'user_id' => $author->id,
    ]);

    expect($creator->notifications()->count())->toBe(1)
        ->and($collaborator->notifications()->count())->toBe(1)
        ->and($attendee->notifications()->count())->toBe(1)
        ->and($author->notifications()->count())->toBe(0);
});

it('sends the comment email separately', function () {
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
        ActivityCommentEmail::class,
        fn (ActivityCommentEmail $notification): bool => $notification->via($rep) === ['mail'],
    );
});
