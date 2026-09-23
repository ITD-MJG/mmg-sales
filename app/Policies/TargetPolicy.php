<?php

namespace App\Policies;

use App\Models\User;

class TargetPolicy extends BasePolicy
{
    protected string $model = 'target';

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo("view_any_{$this->model}");
    }

    public function view(User $user, $model): bool
    {
        if (! $user->hasPermissionTo("view_{$this->model}")) {
            return false;
        }

        // A read-only global viewer (Director in Management) may view any target.
        return $user->hasGlobalVisibility() || $model->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo("create_{$this->model}");
    }

    public function update(User $user, $model): bool
    {
        return $user->hasPermissionTo("update_{$this->model}")
            && $model->user_id === $user->id;
    }

    public function delete(User $user, $model): bool
    {
        return $user->hasPermissionTo("delete_{$this->model}")
            && $model->user_id === $user->id;
    }

    public function restore(User $user, $model): bool
    {
        return $user->hasPermissionTo("restore_{$this->model}");
    }

    public function forceDelete(User $user, $model): bool
    {
        return $user->hasPermissionTo("force_delete_{$this->model}");
    }
}
