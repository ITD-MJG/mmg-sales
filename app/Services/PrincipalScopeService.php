<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for the Marketing department visibility ceiling.
 *
 * A Marketing user sees records linked to the principals assigned to them, plus
 * their own records. A user with no assignment sees only their own records and
 * no principals or products at all; it must never fall through to "see
 * everything".
 */
class PrincipalScopeService
{
    /**
     * Whether the scope applies to this user. Super Admin and Management
     * Director keep global visibility, so the scope never narrows them.
     */
    public static function appliesTo(?User $user): bool
    {
        return $user !== null && ! $user->hasGlobalVisibility() && $user->isMarketingDepartment();
    }

    /**
     * Principals assigned to the user. Empty for a global viewer so the list
     * cannot accidentally be turned into a filter.
     *
     * @return list<int>
     */
    public static function principalIds(User $user): array
    {
        if ($user->hasGlobalVisibility()) {
            return [];
        }

        return $user->assignedPrincipalIds();
    }

    public static function leads(Builder $query, ?User $user): Builder
    {
        if (! self::appliesTo($user)) {
            return $query;
        }

        $principalIds = self::principalIds($user);

        return $query->where(function (Builder $inner) use ($user, $principalIds): void {
            $inner->where('created_by', $user->getKey())
                ->orWhereHas('collaborators', fn (Builder $collaborator): Builder => $collaborator->whereKey($user->getKey()));

            if ($principalIds !== []) {
                $inner->orWhere(fn (Builder $byPrincipal): Builder => $byPrincipal->forPrincipals($principalIds));
            }
        });
    }

    public static function orders(Builder $query, ?User $user): Builder
    {
        if (! self::appliesTo($user)) {
            return $query;
        }

        $principalIds = self::principalIds($user);

        return $query->where(function (Builder $inner) use ($user, $principalIds): void {
            $inner->where('created_by', $user->getKey())
                ->orWhere('sales_id', $user->getKey());

            if ($principalIds !== []) {
                $inner->orWhere(fn (Builder $byPrincipal): Builder => $byPrincipal->forPrincipals($principalIds));
            }
        });
    }

    public static function principals(Builder $query, ?User $user): Builder
    {
        if (! self::appliesTo($user)) {
            return $query;
        }

        $principalIds = self::principalIds($user);

        return $principalIds === []
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('id', $principalIds);
    }

    public static function products(Builder $query, ?User $user): Builder
    {
        if (! self::appliesTo($user)) {
            return $query;
        }

        return $query->forPrincipals(self::principalIds($user));
    }

    /**
     * Apply a principal id list to an order query. Reports use this so the
     * assignment rule is the only rule; it must not OR in the user's own
     * records the way orders() does.
     *
     * @param  array<int, int>|null  $principalIds
     */
    public static function applyToOrders(Builder $query, ?array $principalIds): Builder
    {
        if ($principalIds === null) {
            return $query;
        }

        return $principalIds === []
            ? $query->whereRaw('1 = 0')
            : $query->forPrincipals($principalIds);
    }

    /**
     * @param  array<int, int>|null  $principalIds
     */
    public static function applyToLeads(Builder $query, ?array $principalIds): Builder
    {
        if ($principalIds === null) {
            return $query;
        }

        return $principalIds === []
            ? $query->whereRaw('1 = 0')
            : $query->forPrincipals($principalIds);
    }
}
