<?php

namespace App\Policies;

use App\Models\User;

class OrderPolicy extends BasePolicy
{
    protected string $model = 'order';

    /**
     * Determine whether the user can update the model.
     * Staff can only update their own orders.
     * Supervisors+ can oversee but cannot modify subordinate records.
     */
    public function update(User $user, $model): bool
    {
        // Must have update permission
        if (! $this->allows($user, 'update')) {
            return false;
        }

        // Logistics can update any order (shipping status)
        if ($user->hasRole('Logistics Staff')) {
            return true;
        }

        // Others can only update their own orders
        return $model->created_by === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     * Staff can only delete their own orders.
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
}
