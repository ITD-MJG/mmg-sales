<?php

namespace App\Filament\Resources\Targets\Pages;

use App\Filament\Resources\Targets\TargetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTargets extends ManageRecords
{
    protected static string $resource = TargetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // An annual target is one row with a null month, so the same form
            // cannot add a second one - the user edits the existing row.
            CreateAction::make()
                ->modalDescription('Enter either one annual target for the year or a target for a single month. To change an existing target, edit its row in the table.')
                ->mutateDataUsing(fn (array $data): array => TargetResource::normalizeTargetPayload($data)),
        ];
    }
}
