<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\Pages\ProductReportPage;
use App\Models\Product;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;

class ProductReportResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Product Analysis';

    protected static ?string $slug = 'reports/products';

    public static function getPages(): array
    {
        return [
            'index' => ProductReportPage::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_product_reports');
    }
}
