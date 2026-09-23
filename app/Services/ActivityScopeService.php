<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ActivityScopeService
{
    /**
     * Get a scoped query for activities based on the user's hierarchy.
     */
    public function getActivityQuery(User $user): Builder
    {
        $query = Activity::query();

        // Super Admin and Management Directors see every activity.
        if ($user->hasGlobalVisibility()) {
            return $query;
        }

        // Manager is territory-scoped (under Director)
        $managerRoles = ['Marketing Manager', 'Sales Manager'];
        if ($user->hasAnyRole($managerRoles)) {
            if ($user->territory_id) {
                $territoryUserIds = User::active()
                    ->where('territory_id', $user->territory_id)
                    ->pluck('id');
                $query->whereIn('user_id', $territoryUserIds);
            }

            return $query;
        }

        // Get IDs of all subordinates recursively
        $subordinateIds = $this->getAllSubordinateIds($user);

        // Include the user's own ID
        $allowedUserIds = $subordinateIds->push($user->id);

        // Include activities from leads where user is a collaborator
        $collaboratorLeadIds = $this->getCollaboratorLeadIds($user);

        return $query->where(function ($q) use ($allowedUserIds, $collaboratorLeadIds, $user) {
            $q->whereIn('user_id', $allowedUserIds);

            if ($collaboratorLeadIds->isNotEmpty()) {
                $q->orWhere(function ($oq) use ($collaboratorLeadIds, $user) {
                    $oq->whereIn('lead_id', $collaboratorLeadIds);

                    if ($user->territory_id) {
                        $oq->whereHas('user', function ($uq) use ($user): void {
                            $uq->where('territory_id', $user->territory_id);
                        });
                    }
                });
            }
        });
    }

    /**
     * Get lead IDs where the user is a collaborator or creator.
     */
    public function getCollaboratorLeadIds(User $user): Collection
    {
        $collaboratorIds = \DB::table('lead_collaborators')
            ->where('user_id', $user->id)
            ->pluck('lead_id');

        $creatorIds = \DB::table('leads')
            ->where('created_by', $user->id)
            ->pluck('id');

        return $collaboratorIds->merge($creatorIds)->unique();
    }

    /**
     * Get all subordinate IDs recursively for a given user.
     */
    public function getAllSubordinateIds(User $user): Collection
    {
        // Super Admin and Management department see all territories
        if ($user->hasGlobalVisibility()) {
            return User::active()->pluck('id');
        }

        // Manager is territory-scoped
        $managerRoles = ['Marketing Manager', 'Sales Manager'];
        if ($user->hasAnyRole($managerRoles)) {
            $query = User::active();

            if ($user->territory_id) {
                $query->where('territory_id', $user->territory_id);
            }

            return $query->pluck('id');
        }

        $subordinateIds = collect();

        foreach ($user->subordinates as $subordinate) {
            $subordinateIds->push($subordinate->id);
            $subordinateIds = $subordinateIds->merge($this->getAllSubordinateIds($subordinate));
        }

        return $subordinateIds->unique();
    }

    /**
     * Get activity statistics for a user's scope.
     * Optionally filter by type for "visit" statistics.
     */
    public function getActivityStats(User $user, ?array $types = null): array
    {
        $query = $this->getActivityQuery($user);

        if ($types) {
            $query->whereIn('type', $types);
        }

        $currentMonthActivities = (clone $query)
            ->whereYear('performed_at', now()->year)
            ->whereMonth('performed_at', now()->month)
            ->count();

        $lastMonthActivities = (clone $query)
            ->whereYear('performed_at', now()->subMonth()->year)
            ->whereMonth('performed_at', now()->subMonth()->month)
            ->count();

        $growth = 0;
        if ($lastMonthActivities > 0) {
            $growth = (($currentMonthActivities - $lastMonthActivities) / $lastMonthActivities) * 100;
        } elseif ($currentMonthActivities > 0) {
            $growth = 100;
        }

        // Top SR count with most activities to a customer
        $topRepCustomer = (clone $query)
            ->selectRaw('user_id, customer_id, count(*) as activity_count')
            ->whereNotNull('customer_id')
            ->groupBy('user_id', 'customer_id')
            ->orderByDesc('activity_count')
            ->first();

        return [
            'total' => (clone $query)->count(),
            'monthly' => $currentMonthActivities,
            'growth' => round($growth, 1),
            'top_rep_customer_count' => $topRepCustomer?->activity_count ?? 0,
            'top_rep_name' => $topRepCustomer?->user?->name ?? 'N/A',
        ];
    }

    /**
     * Get recent activities for a user's scope.
     */
    public function getRecentActivities(User $user, int $limit = 5, ?array $types = null): Collection
    {
        $query = $this->getActivityQuery($user)
            ->with(['customer', 'user']);

        if ($types) {
            $query->whereIn('type', $types);
        }

        return $query->latest('performed_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get query for leaderboard grouped by rep and customer.
     */
    public function getRepCustomerLeaderboardQuery(User $user, ?array $types = null): Builder
    {
        $query = Activity::query()
            ->whereIn('user_id', $this->getAllSubordinateIds($user)->push($user->id))
            ->whereNotNull('customer_id');

        if ($types) {
            $query->whereIn('type', $types);
        }

        return $query->selectRaw('user_id, customer_id, count(*) as activity_count')
            ->groupBy('user_id', 'customer_id')
            ->with(['user:id,name', 'customer:id,name'])
            ->orderByDesc('activity_count');
    }

    /**
     * Get activity statistics for a specific customer within user's scope.
     */
    public function getCustomerActivityStats(User $user, int $customerId): array
    {
        $query = $this->getActivityQuery($user)->where('customer_id', $customerId);

        $total = (clone $query)->count();
        $lastActivity = (clone $query)->latest('performed_at')->first();

        return [
            'total' => $total,
            'last_activity_date' => $lastActivity?->performed_at,
        ];
    }
}
