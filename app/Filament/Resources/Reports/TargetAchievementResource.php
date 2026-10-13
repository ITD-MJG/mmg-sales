<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\Pages\TargetAchievementPage;
use App\Models\Target;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;

class TargetAchievementResource extends Resource
{
    protected static ?string $model = Target::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Target Achievement';

    protected static ?string $slug = 'reports/target';

    public static function getPages(): array
    {
        return [
            'index' => TargetAchievementPage::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_target_reports');
    }
}
