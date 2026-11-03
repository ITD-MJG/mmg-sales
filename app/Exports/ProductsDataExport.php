<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class ProductsDataExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    public function collection(): Collection
    {
        return Product::query()
            ->select([
                'products.name',
                'products.internal_code',
                'products.product_acc_code',
                'products.unit_of_measure',
                'products.category',
                'principals.name as principal_name',
            ])
            ->leftJoin('principals', 'principals.id', '=', 'products.principal_id')
            ->orderBy('products.name')
            ->get();
    }

    public function headings(): array
    {
        return ProductsTemplateExport::HEADINGS;
    }

    public function map($row): array
    {
        return [
            $row->principal_name,
            $row->internal_code,
            $row->product_acc_code,
            $row->name,
            $row->unit_of_measure,
            $row->category,
        ];
    }

    public function title(): string
    {
        return 'Products';
    }
}
