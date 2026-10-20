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
     * - Others: own records + position descendants and direct reports,
     *   territory-scoped when they have a territory
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

        // Staff - can only see their own records
        if ($user->hasAnyRole(Role::names(Role::ownRecordsOnly()))) {
            return $query->where($userColumn, $user->id);
        }

        // Other users: own records plus everything reachable through the
        // position hierarchy and the reporting line.
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
     * Get IDs of all subordinate users: descendants of the user's position,
     * plus users who report to them via manager_id.
     *
     * When the user has a territory, both groups are restricted to it — that
     * scoping stops a manager seeing another territory's pipeline, and stops
     * peers in a shared position from seeing each other's records. When the
     * user has no territory, the position and reporting-line relationships
     * alone decide visibility.
     */
    private static function getSubordinateUserIds($user): array
    {
        $userIds = [];

        // Descendant positions. Territory scoping only applies when the manager
        // actually has a territory: `territory_id` is optional, and requiring it
        // here silently reduced a territory-less manager to own-records-only.
        if ($user->position) {
            $descendantPositionIds = array_diff(
                $user->position->getAllDescendantIds(),
                [$user->position_id]
            );

            $positionUserIds = User::active()
                ->whereIn('position_id', $descendantPositionIds)
                ->when($user->territory_id, fn ($query, $territoryId) => $query->where('territory_id', $territoryId))
                ->where('id', '!=', $user->id)
                ->pluck('id')
                ->toArray();

            $userIds = array_merge($userIds, $positionUserIds);
        }

        // Direct reports, same territory scoping rule as above.
        $directReportIds = User::active()
            ->where('manager_id', $user->id)
            ->when($user->territory_id, fn ($query, $territoryId) => $query->where('territory_id', $territoryId))
            ->pluck('id')
            ->toArray();

        $userIds = array_merge($userIds, $directReportIds);

        return array_unique($userIds);
    }
}
