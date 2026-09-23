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
        if (! $this->allows($user, 'update')) {
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
        if (! $this->allows($user, 'delete')) {
            return false;
        }

        return $model->user_id === $user->id;
    }

    public function createForLead(User $user, $lead): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        if ($lead->created_by === $user->id) {
            return true;
        }

        return $lead->collaborators()->where('user_id', $user->id)->exists();
    }
}
