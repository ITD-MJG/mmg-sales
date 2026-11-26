<?php

namespace App\Filament\Resources\Activities\Pages;

use App\Filament\Resources\Activities\ActivityResource;
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

        $tabs = [];

        // A global viewer (Super Admin, Management Director) has no ownership
        // line: no subordinates means no team tab, and their own activity log
        // is usually empty, so "My Activities" is the only tab that renders
        // and the list looks unreachable. Give them the unfiltered view and
        // open on it.
        if ($user?->hasGlobalVisibility()) {
            $tabs['all'] = Tab::make('All Activities');
        }

        $tabs['my'] = Tab::make('My Activities')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('user_id', $user?->id));

        $teamIds = $user?->subordinateUserIds() ?? [];

        if ($teamIds !== []) {
            $tabs['team'] = Tab::make('My Team Activities')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('user_id', $teamIds));
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string
    {
        return auth()->user()?->hasGlobalVisibility() ? 'all' : 'my';
    }
}
