<?php

namespace App\Policies;

use App\Models\User;
use App\Services\PrincipalScopeService;

class OrderPolicy extends BasePolicy
{
    protected string $model = 'order';

    /**
     * A direct URL to an order must respect the same ceiling as the list.
     * Admins and non-Marketing users keep the permission-only rule.
     */
    public function view(User $user, mixed $model): bool
    {
        if (! $user->hasPermissionTo("view_{$this->model}")) {
            return false;
        }

        if (! PrincipalScopeService::appliesTo($user)) {
            return true;
        }

        return $model->created_by === $user->id
            || $model->sales_id === $user->id
            || $model->hasAnyAssignedPrincipal(PrincipalScopeService::principalIds($user));
    }

    /**
     * Determine whether the user can update the model.
     * Staff can only update their own orders.
     * Supervisors+ can oversee but cannot modify subordinate records.
     */
    public function update(User $user, $model): bool
    {
        // Must have update permission
        if (! $user->hasPermissionTo("update_{$this->model}")) {
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
        if (! $user->hasPermissionTo("delete_{$this->model}")) {
            return false;
        }

        return $model->created_by === $user->id;
    }
}
