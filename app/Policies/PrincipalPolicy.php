<?php

namespace App\Policies;

use App\Models\User;
use App\Services\PrincipalScopeService;

class PrincipalPolicy extends BasePolicy
{
    protected string $model = 'principal';

    /**
     * A direct URL must respect the same ceiling as the list: a Marketing user
     * may only open the principals assigned to them.
     */
    public function view(User $user, mixed $model): bool
    {
        if (! $user->hasPermissionTo("view_{$this->model}")) {
            return false;
        }

        if (! PrincipalScopeService::appliesTo($user)) {
            return true;
        }

        return $user->managesPrincipal($model);
    }
}
