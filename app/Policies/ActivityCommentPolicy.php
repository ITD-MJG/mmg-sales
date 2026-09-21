<?php

namespace App\Policies;

use App\Models\User;

class ActivityCommentPolicy extends BasePolicy
{
    protected string $model = 'activity_comment';

    protected array $authorizedRoles = ['Super Admin'];

    /**
     * A comment may be added by anyone attached to the activity: the rep who
     * logged it, a listed attendee, or the creator/collaborator of its lead.
     * Requires the create permission on top of that relationship.
     */
    public function createForActivity(User $user, $activity): bool
    {
        if (! $user->hasPermissionTo("create_{$this->model}")) {
            return false;
        }

        return $activity->isAccessibleBy($user);
    }

    public function update(User $user, $model): bool
    {
        if (! $user->hasPermissionTo("update_{$this->model}")) {
            return false;
        }

        return $model->user_id === $user->id;
    }

    public function delete(User $user, $model): bool
    {
        if (! $user->hasPermissionTo("delete_{$this->model}")) {
            return false;
        }

        return $model->user_id === $user->id;
    }
}
