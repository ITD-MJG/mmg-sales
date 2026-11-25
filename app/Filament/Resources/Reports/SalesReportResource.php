<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\Pages\SalesReportPage;
use App\Models\Order;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;

class SalesReportResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Sales Performance';

    protected static ?string $slug = 'reports/sales';

    public static function getPages(): array
    {
        return [
            'index' => SalesReportPage::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_report_sales') ?? false;
    }
}
