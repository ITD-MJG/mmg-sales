<?php

namespace App\Observers;

use App\Models\ActivityComment;
use App\Models\User;
use App\Notifications\ActivityCommentPosted;
use Illuminate\Support\Collection;

class ActivityCommentObserver
{
    /**
     * Notify everyone attached to the activity — the rep who logged it, its
     * attendees, and the parent opportunity's creator and collaborators —
     * except whoever wrote the comment.
     */
    public function created(ActivityComment $comment): void
    {
        $activity = $comment->activity;

        if (! $activity) {
            return;
        }

        $recipients = $this->recipients($activity)
            ->reject(fn (User $user): bool => $user->is($comment->user))
            ->unique('id');

        foreach ($recipients as $recipient) {
            $recipient->notify(new ActivityCommentPosted($comment));
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients($activity): Collection
    {
        $users = User::query()
            ->whereKey($activity->user_id)
            ->get();

        $users = $users->merge($activity->attendees);

        $opportunity = $activity->opportunity;

        if ($opportunity) {
            $users = $users
                ->merge(User::query()->whereKey($opportunity->created_by)->get())
                ->merge($opportunity->collaborators);
        }

        return $users;
    }
}
