<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\Pages\CustomerReportPage;
use App\Models\Customer;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;

class CustomerReportResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Customer Insights';

    protected static ?string $slug = 'reports/customers';

    public static function getPages(): array
    {
        return [
            'index' => CustomerReportPage::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_report_customers') ?? false;
    }
}
