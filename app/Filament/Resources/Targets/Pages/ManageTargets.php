<?php

namespace App\Filament\Resources\Targets\Pages;

use App\Filament\Resources\Targets\TargetResource;
use App\Models\Target;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTargets extends ManageRecords
{
    protected static string $resource = TargetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalDescription('Enter one target per representative per month. Edit an existing row to report its actual.')
                ->using(function (array $data): Target {
                    // Fixed rows make a duplicate month unlikely, but a repeated
                    // month in the payload must still land as one row.
                    $rows = collect($data['monthly_targets'])
                        ->filter(fn (array $row): bool => filled($row['monthly_target'] ?? null))
                        ->keyBy(fn (array $row): int => (int) $row['month']);

                    foreach ($rows as $month => $row) {
                        Target::create([
                            'user_id' => $data['user_id'],
                            'year' => $data['year'],
                            'month' => $month,
                            'monthly_target' => $row['monthly_target'],
                        ]);
                    }

                    return Target::query()->where('user_id', $data['user_id'])
                        ->where('year', $data['year'])
                        ->orderBy('month')
                        ->firstOrFail();
                }),
        ];
    }
}
