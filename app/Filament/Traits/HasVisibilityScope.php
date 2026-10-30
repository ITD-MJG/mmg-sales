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
        $ids = self::visibleUserIds();

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($userColumn, $ids);
    }

    /**
     * Scope to the user's visible records, but keep rows whose owner is NULL
     * visible to everyone.
     *
     * Orders predate per-user attribution: `sales_id` arrived on 2026-10-28 and
     * every pre-existing row is NULL, so a strict scope would blank the order
     * widgets for the whole company. Unattributed rows stay shared until they
     * are assigned a salesperson; once `sales_id` is filled the scope narrows
     * on its own.
     */
    public static function applyLegacySharedScope(Builder $query, string $userColumn): Builder
    {
        $ids = self::visibleUserIds();

        if ($ids === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($userColumn, $ids): void {
            $q->whereIn($userColumn, $ids)->orWhereNull($userColumn);
        });
    }

    /**
     * The user ids whose records the current user may see, or null when they
     * see every record.
     *
     * - No authenticated user: null, callers leave the query alone
     * - Super Admin / Management Director: null, everything
     * - Own-record roles: the user alone, deliberately ignoring subordinates
     * - Everyone else: the user plus their reporting-line or territory users
     *
     * @return list<int>|null
     */
    public static function visibleUserIds(?User $user = null): ?array
    {
        $user ??= auth()->user();

        if (! $user || $user->hasGlobalVisibility()) {
            return null;
        }

        if ($user->hasAnyRole(Role::names(Role::ownRecordsOnly()))) {
            return [$user->id];
        }

        return array_values(array_unique([
            $user->id,
            ...self::getSubordinateUserIds($user),
        ]));
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
