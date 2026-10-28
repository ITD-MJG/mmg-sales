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

        // Super Admin and Management Directors see every activity.
        if ($user->hasGlobalVisibility()) {
            return $query;
        }

        $subordinateIds = $user->hasAnyRole(\App\Enums\Role::names(\App\Enums\Role::territoryScoped())) && $user->territory_id
            ? User::active()->where('territory_id', $user->territory_id)->pluck('id')
            : $this->getAllSubordinateIds($user);
        $collaboratorOpportunityIds = $this->getCollaboratorOpportunityIds($user);

        return $query->where(function ($q) use ($subordinateIds, $collaboratorOpportunityIds, $user) {
            $q->whereIn('user_id', $subordinateIds->push($user->id));

            if ($collaboratorOpportunityIds->isNotEmpty()) {
                $q->orWhereIn('opportunity_id', $collaboratorOpportunityIds);
            }
        });
    }

    /**
     * Get opportunity IDs where the user is a collaborator or creator.
     */
    public function getCollaboratorOpportunityIds(User $user): Collection
    {
        $collaboratorIds = \DB::table('opportunity_collaborators')
            ->where('user_id', $user->id)
            ->pluck('opportunity_id');

        $creatorIds = \DB::table('opportunities')
            ->where('created_by', $user->id)
            ->pluck('id');

        return $collaboratorIds->merge($creatorIds)->unique();
    }

    /**
     * Get all subordinate IDs recursively for a given user.
     */
    public function getAllSubordinateIds(User $user): Collection
    {
        if ($user->hasGlobalVisibility()) {
            return User::active()->pluck('id');
        }

        $subordinateIds = collect();
        $pending = collect([$user->id]);

        while ($pending->isNotEmpty()) {
            $managerId = $pending->pop();
            $reportIds = User::active()->where('manager_id', $managerId)->pluck('id');
            $subordinateIds = $subordinateIds->merge($reportIds);
            $pending = $pending->merge($reportIds);
        }

        return $subordinateIds->unique()->values();
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
