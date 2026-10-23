<?php

namespace App\Notifications;

use App\Models\ActivityComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ActivityCommentEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ActivityComment $comment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $activity = $this->comment->activity;

        return (new MailMessage)
            ->subject('New comment on '.($activity?->activity_code ?? 'an activity'))
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->comment->user->name.' commented on the activity "'.($activity?->subject ?? '-').'".')
            ->line('"'.$this->comment->comment.'"')
            ->line('Activity code: '.($activity?->activity_code ?? '-'));
    }
}
