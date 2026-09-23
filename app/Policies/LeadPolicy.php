<?php

namespace App\Policies;

use App\Models\User;

class LeadPolicy extends BasePolicy
{
    protected string $model = 'lead';

    protected array $authorizedRoles = ['Super Admin'];

    /**
     * Determine whether the user can update the model.
     * Staff can only update their own leads.
     * Supervisors+ can oversee but cannot modify subordinate records.
     */
    public function update(User $user, $model): bool
    {
        // Must have update permission AND be the creator
        if (! $this->allows($user, 'update')) {
            return false;
        }

        return $model->created_by === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     * Staff can only delete their own leads.
     * Supervisors+ can oversee but cannot delete subordinate records.
     */
    public function delete(User $user, $model): bool
    {
        // Must have delete permission AND be the creator
        if (! $this->allows($user, 'delete')) {
            return false;
        }

        return $model->created_by === $user->id;
    }

    public function addCollaborator(User $user, $lead): bool
    {
        return $this->isCreator($user, $lead);
    }

    public function removeCollaborator(User $user, $lead): bool
    {
        return $this->isCreator($user, $lead);
    }

    private function isCreator(User $user, $lead): bool
    {
        return $lead->created_by === $user->id;
    }
}
