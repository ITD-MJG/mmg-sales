<?php

namespace App\Filament\Traits;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait HasVisibilityScope
{
    /**
     * Apply the record-visibility contract for the current user.
     *
     * - Super Admin: sees everything, writes everything
     * - Management department Director: sees everything, writes nothing
     * - Staff: own records only
     * - Other managers: own records plus their reporting-line subordinates
     * - Territory managers: own records plus territory users
     */
    public static function applyVisibilityScope(Builder $query, string $userColumn = 'user_id'): Builder
    {
        $user = auth()->user();

        if (! $user) {
            return $query;
        }

        // Super Admin and Management Directors see everything.
        if ($user->hasGlobalVisibility()) {
            return $query;
        }

        // Own-record roles can only see their own records
        if ($user->hasAnyRole(Role::names(Role::ownRecordsOnly()))) {
            return $query->where($userColumn, $user->id);
        }

        // Other users: own records plus the reporting-line users
        // Territory-scoped roles add their assigned-territory users.
        $subordinateIds = self::getSubordinateUserIds($user);

        if (! empty($subordinateIds)) {
            return $query->where(function ($q) use ($user, $userColumn, $subordinateIds) {
                $q->where($userColumn, $user->id) // Own records
                    ->orWhereIn($userColumn, $subordinateIds); // Subordinate records
            });
        }

        // Default: own records only
        return $query->where($userColumn, $user->id);
    }

    /**
     * Check if user can edit/delete a specific record.
     *
     * - Super Admin: can modify anything
     * - Management department: view-only (cannot modify any records)
     * - Everyone else: can only modify their own records
     */
    public static function canModifyRecord($record, string $userColumn = 'user_id'): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // Super Admin can modify anything
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Management department is view-only, cannot modify any records
        if ($user->isReadOnlyGlobalViewer()) {
            return false;
        }

        // Everyone else: can only modify their own records
        return $record->{$userColumn} === $user->id;
    }

    /**
     * Resolve users visible by territory or manager reporting line.
     */
    private static function getSubordinateUserIds(User $user): array
    {

        if ($user->hasAnyRole(Role::names(Role::territoryScoped())) && $user->territory_id) {
            return User::active()
                ->where('territory_id', $user->territory_id)
                ->where('id', '!=', $user->id)
                ->pluck('id')
                ->toArray();
        }

        return $user->subordinateUserIds();
    }
}
