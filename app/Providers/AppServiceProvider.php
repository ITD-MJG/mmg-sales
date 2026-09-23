<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerSuperAdminBypass();
    }

    /**
     * Super Admin bypasses every permission check.
     *
     * The role is seeded without any permissions (see RolesAndPermissionsSeeder)
     * and relies on this single grant-all. Without it a Super Admin is denied
     * every resource whose policy does not happen to name the role, which is
     * how the role silently lost access to customers, orders and products.
     */
    private function registerSuperAdminBypass(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->isSuperAdmin() ? true : null;
        });
    }
}
