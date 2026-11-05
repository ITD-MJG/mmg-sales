<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\Pages\SalesStatisticsPage;
use App\Models\Lead;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;

class SalesStatisticsResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Sales Statistics';

    protected static ?string $slug = 'crm/sales-statistics';

    public static function getPages(): array
    {
        return [
            'index' => SalesStatisticsPage::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_sales_statistics');
    }
}
