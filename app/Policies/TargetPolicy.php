<?php

namespace App\Policies;

use App\Models\User;

class TargetPolicy extends BasePolicy
{
    protected string $model = 'target';

    public function view(User $user, $model): bool
    {
        if (! $this->allows($user, 'view')) {
            return false;
        }

        // A read-only global viewer (Director in Management) may view any target.
        return $user->hasGlobalVisibility() || $model->user_id === $user->id;
    }

    public function update(User $user, $model): bool
    {
        return $this->allows($user, 'update')
            && $model->user_id === $user->id;
    }

    public function delete(User $user, $model): bool
    {
        return $this->allows($user, 'delete')
            && $model->user_id === $user->id;
    }

    // viewAny, create, restore and forceDelete are inherited from BasePolicy.
}
