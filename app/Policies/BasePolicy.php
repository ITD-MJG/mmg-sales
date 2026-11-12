<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

abstract class BasePolicy
{
    use HandlesAuthorization;

    protected string $model;

    /**
     * Roles that have full access to this resource
     */
    protected array $authorizedRoles = [];

    /**
     * Abilities that destroy data. A global viewer (a Director in the
     * Management department) may create and edit, but never delete.
     *
     * @var list<string>
     */
    protected array $destructiveAbilities = [
        'delete', 'restore', 'forceDelete',
    ];

    public function before(User $user, string $ability): ?bool
    {
        // A Director in the Management department sees every record and may
        // create and edit Leads and Activities, but never destroys one.
        if ($user->isGlobalViewer() && in_array($ability, $this->destructiveAbilities, true)) {
            return false;
        }

        // Only consider roles whose department matches the user's department (or global)
        $validRoles = $user->roles->filter(function ($role) use ($user) {
            return is_null($role->department_id)
                || $role->department_id === $user->department_id;
        });

        // User has roles but none match their current department
        if ($validRoles->isEmpty() && $user->roles->isNotEmpty()) {
            return false;
        }

        // Check authorizedRoles bypass against valid roles only
        if (! empty($this->authorizedRoles)) {
            $validRoleNames = $validRoles->pluck('name');

            if ($validRoleNames->intersect($this->authorizedRoles)->isNotEmpty()) {
                return true;
            }
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo("view_any_{$this->model}");
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, mixed $model): bool
    {
        return $user->hasPermissionTo("view_{$this->model}");
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo("create_{$this->model}");
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, $model): bool
    {
        return $user->hasPermissionTo("update_{$this->model}");
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, $model): bool
    {
        return $user->hasPermissionTo("delete_{$this->model}");
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, $model): bool
    {
        return $user->hasPermissionTo("restore_{$this->model}");
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, $model): bool
    {
        return $user->hasPermissionTo("force_delete_{$this->model}");
    }
}
