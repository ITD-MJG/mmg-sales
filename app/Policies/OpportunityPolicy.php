<?php

namespace App\Policies;

use App\Models\User;

class OpportunityPolicy extends BasePolicy
{
    protected string $model = 'opportunity';

    protected array $authorizedRoles = ['Super Admin'];

    /**
     * Determine whether the user can update the model.
     * Staff can only update their own opportunities.
     * Supervisors+ can oversee but cannot modify subordinate records.
     */
    public function update(User $user, $model): bool
    {
        // Must have update permission AND be the creator
        if (! $user->hasPermissionTo("update_{$this->model}")) {
            return false;
        }

        return $model->created_by === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     * Staff can only delete their own opportunities.
     * Supervisors+ can oversee but cannot delete subordinate records.
     */
    public function delete(User $user, $model): bool
    {
        // Must have delete permission AND be the creator
        if (! $user->hasPermissionTo("delete_{$this->model}")) {
            return false;
        }

        return $model->created_by === $user->id;
    }

    public function addCollaborator(User $user, $opportunity): bool
    {
        return $this->isCreator($user, $opportunity);
    }

    public function removeCollaborator(User $user, $opportunity): bool
    {
        return $this->isCreator($user, $opportunity);
    }

    private function isCreator(User $user, $opportunity): bool
    {
        return $opportunity->created_by === $user->id;
    }
}
