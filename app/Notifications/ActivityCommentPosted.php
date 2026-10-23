<?php

namespace App\Notifications;

use App\Filament\Resources\Activities\ActivityResource;
use App\Models\ActivityComment;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification as LaravelNotification;

class ActivityCommentPosted extends LaravelNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ActivityComment $comment,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $activity = $this->comment->activity;

        $notification = FilamentNotification::make()
            ->title('New comment on '.($activity?->activity_code ?? 'an activity'))
            ->body($this->comment->user->name.' commented: '.str($this->comment->comment)->limit(80))
            ->info()
            ->persistent()
            ->actions($activity ? [
                Action::make('viewActivity')
                    ->label('View Activity')
                    ->url(ActivityResource::getUrl('view', ['record' => $activity])),
            ] : []);

        return array_merge($notification->toDatabase()->data, [
            'activity_id' => $activity?->id,
            'comment_id' => $this->comment->id,
        ]);
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
