<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class ProductsDataExport implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize
{
    public function collection(): Collection
    {
        return Product::query()
            ->select([
                'products.name',
                'products.category',
                'products.description',
                'products.internal_code',
                'products.unit_of_measure',
                'products.is_active',
                'principals.name as principal_name',
            ])
            ->leftJoin('principals', 'principals.id', '=', 'products.principal_id')
            ->orderBy('products.name')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Principal Name',
            'Internal Code',
            'Product Name',
            'Category',
            'Description',
            'Unit of Measure',
            'Is Active',
        ];
    }

    public function map($row): array
    {
        return [
            $row->principal_name,
            $row->internal_code,
            $row->name,
            $row->category,
            $row->description,
            $row->unit_of_measure,
            $row->is_active,
        ];
    }

    public function title(): string
    {
        return 'Products';
    }
}
