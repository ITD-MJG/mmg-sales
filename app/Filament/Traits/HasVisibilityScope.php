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
     * Apply the record-visibility contract to leads.
     *
     * A lead is "owned" by its creator or by any of its collaborators: the
     * `assigned_to` column was retired into `lead_collaborators`, so matching
     * `created_by` alone would hide a rep's leads from the rep they were handed
     * to. Everyone else still resolves through `visibleUserIds()`, so managers
     * keep seeing their team's leads and global viewers see everything.
     */
    public static function applyLeadVisibilityScope(Builder $query, string $creatorColumn = 'created_by'): Builder
    {
        $ids = self::visibleUserIds();

        if ($ids === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($creatorColumn, $ids): void {
            $q->whereIn($creatorColumn, $ids)
                ->orWhereHas('collaborators', fn (Builder $collaborators): Builder => $collaborators->whereIn('users.id', $ids));
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

        // A Management Director may edit any record (delete stays denied by
        // the policy layer).
        if ($user->isGlobalViewer()) {
            return true;
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
