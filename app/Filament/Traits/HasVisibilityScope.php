<?php

namespace App\Filament\Traits;

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
     * - Others: own records + subordinates (position OR territory union) + direct reports
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
        $staffRoles = ['Sales Staff', 'Marketing Staff', 'Logistics Staff', 'Finance & Accounting Staff'];
        if ($user->hasAnyRole($staffRoles)) {
            return $query->where($userColumn, $user->id);
        }

        // Other users: resolve subordinates via position OR territory union,
        // plus direct reports via manager_id
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
     * Get IDs of all subordinate users based on position OR territory hierarchy,
     * plus direct reports (manager_id).
     *
     * Logic:
     *   subordinateIds = (positionDescendants ∪ territoryDescendants) ∪ directReports
     */
    private static function getSubordinateUserIds($user): array
    {
        $userIds = [];

        // Users in descendant positions AND same territory
        if ($user->position && $user->territory_id) {
            $descendantPositionIds = array_diff(
                $user->position->getAllDescendantIds(),
                [$user->position_id]
            );

            $positionUserIds = User::active()
                ->whereIn('position_id', $descendantPositionIds)
                ->where('territory_id', $user->territory_id)
                ->where('id', '!=', $user->id)
                ->pluck('id')
                ->toArray();

            $userIds = array_merge($userIds, $positionUserIds);
        }

        // Direct reports in same territory
        if ($user->territory_id) {
            $directReportIds = User::active()
                ->where('manager_id', $user->id)
                ->where('territory_id', $user->territory_id)
                ->pluck('id')
                ->toArray();

            $userIds = array_merge($userIds, $directReportIds);
        }

        return array_unique($userIds);
    }
}
