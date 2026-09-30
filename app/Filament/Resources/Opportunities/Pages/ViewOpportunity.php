<?php

namespace App\Filament\Resources\Opportunities\Pages;

use App\Filament\Resources\Opportunities\OpportunityResource;
use App\Filament\Resources\Opportunities\Tables\OpportunitiesTable;
use App\Filament\Traits\HasRecordNavigation;
use App\Models\Opportunity;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Builder;

class ViewOpportunity extends ViewRecord
{
    use HasRecordNavigation;

    protected static string $resource = OpportunityResource::class;

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
        return OpportunitiesTable::listVisibilityQuery(Opportunity::query());
    }

    protected function navigationSortColumn(): string
    {
        return 'opportunity_code';
    }
}
