<?php

namespace App\Policies;

use App\Models\User;

class ActivityPolicy extends BasePolicy
{
    protected string $model = 'activity';

    /**
     * Determine whether the user can update the model.
     * Staff can only update their own activities.
     * Supervisors+ can oversee but cannot modify subordinate records.
     */
    public function update(User $user, $model): bool
    {
        // Must have update permission AND be the owner
        if (! $user->hasPermissionTo("update_{$this->model}")) {
            return false;
        }

        return $model->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     * Staff can only delete their own activities.
     * Supervisors+ can oversee but cannot delete subordinate records.
     */
    public function delete(User $user, $model): bool
    {
        // Must have delete permission AND be the owner
        if (! $user->hasPermissionTo("delete_{$this->model}")) {
            return false;
        }

        return $model->user_id === $user->id;
    }

    /**
     * A thin lead has no collaborators — attachment is an opportunity concept.
     * Only its creator (and a Super Admin) may log activities against it.
     */
    public function createForLead(User $user, $lead): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return $lead->created_by === $user->id;
    }
}
