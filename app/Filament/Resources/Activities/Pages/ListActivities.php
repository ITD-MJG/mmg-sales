<?php

namespace App\Filament\Resources\Activities\Pages;

use App\Enums\Role;
use App\Filament\Resources\Activities\ActivityResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $user = auth()->user();
        $teamUserIds = $user ? self::teamUserIds($user) : [];

        $tabs = [
            'my' => Tab::make('My Activities')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where(function (Builder $query) use ($user): void {
                    $query->where('user_id', $user->id)
                        ->orWhereHas('attendees', fn (Builder $attendees): Builder => $attendees->whereKey($user->id));
                })),
        ];

        if ($teamUserIds !== []) {
            $tabs['team'] = Tab::make('My Team Activities')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('user_id', $teamUserIds));
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string
    {
        return 'my';
    }

    /** @return list<int> */
    private static function teamUserIds(User $user): array
    {
        $userIds = $user->subordinateUserIds();

        if ($user->hasAnyRole(Role::names(Role::territoryScoped())) && $user->territory_id) {
            $userIds = array_merge(
                $userIds,
                User::active()
                    ->where('territory_id', $user->territory_id)
                    ->where('id', '!=', $user->id)
                    ->pluck('id')
                    ->all(),
            );
        }

        return array_values(array_unique($userIds));
    }
}
