<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\Pages\PipelineReportPage;
use App\Models\Lead;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;

class PipelineReportResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Lead Report';

    protected static ?string $slug = 'reports/lead';

    public static function getPages(): array
    {
        return [
            'index' => PipelineReportPage::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_report_leads') ?? false;
    }
}
