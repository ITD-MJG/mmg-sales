<?php

namespace App\Filament\Resources\Activities\Pages;

use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Activities\Tables\ActivitiesTable;
use App\Filament\Traits\HasRecordNavigation;
use App\Models\Activity;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Builder;

class ViewActivity extends ViewRecord
{
    use HasRecordNavigation;

    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->previousRecordAction(),
            $this->nextRecordAction(),
            EditAction::make(),
        ];
    }

    protected function navigationQuery(): Builder
    {
        return ActivitiesTable::listVisibilityQuery(Activity::query());
    }

    protected function navigationSortColumn(): string
    {
        return 'activity_code';
    }
}
