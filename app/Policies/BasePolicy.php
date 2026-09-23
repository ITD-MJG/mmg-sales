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
     * Abilities that mutate data. A read-only global viewer (a Director in
     * the Management department) is denied every one of these, including the
     * custom abilities that are not named after a CRUD verb.
     *
     * @var list<string>
     */
    protected array $writeAbilities = [
        'create', 'update', 'delete', 'restore', 'forceDelete',
        'createForLead', 'createForActivity',
        'addCollaborator', 'removeCollaborator',
    ];

    public function before(User $user, string $ability): ?bool
    {
        // A Director in the Management department is a read-only global viewer:
        // they may see every record but may never write one.
        if ($user->isReadOnlyGlobalViewer() && in_array($ability, $this->writeAbilities, true)) {
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
     * Whether the user holds the permission backing an ability.
     *
     * checkPermissionTo() is deliberate: hasPermissionTo() throws
     * PermissionDoesNotExist, and these methods run for every resource on every
     * render (Filament renders global search across the whole panel). One
     * missing permission row would otherwise 500 the entire admin. A permission
     * that does not exist is one nobody can hold, so denying is correct.
     */
    protected function allows(User $user, string $ability): bool
    {
        return $user->checkPermissionTo("{$ability}_{$this->model}");
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view_any');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, $model): bool
    {
        return $this->allows($user, 'view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, $model): bool
    {
        return $this->allows($user, 'update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, $model): bool
    {
        return $this->allows($user, 'delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, $model): bool
    {
        return $this->allows($user, 'restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, $model): bool
    {
        return $this->allows($user, 'force_delete');
    }
}
