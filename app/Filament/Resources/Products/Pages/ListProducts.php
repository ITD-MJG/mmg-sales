<?php

namespace App\Filament\Resources\Products\Pages;

use App\Exports\ProductsDataExport;
use App\Exports\ProductsTemplateExport;
use App\Filament\Actions\ImportProductsAction;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Maatwebsite\Excel\Facades\Excel;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportProductsAction::make(),
            Action::make('export_products')
                ->label('Export Products')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->action(fn () => Excel::download(new ProductsDataExport, 'products.xlsx')),
            Action::make('download_template')
                ->label('Download Template')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->visible(fn () => auth()->user()->hasRole('Super Admin'))
                ->action(fn () => Excel::download(new ProductsTemplateExport, 'products_template.xlsx')),
            CreateAction::make(),
        ];
    }
}
