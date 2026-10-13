<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class ProductsDataExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function collection(): Collection
    {
        return Product::query()
            ->select([
                'products.name',
                'products.category',
                'products.description',
                'products.internal_code',
                'products.unit_price',
                'products.ecatalog_price',
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
            'name',
            'category',
            'description',
            'internal_code',
            'unit_price',
            'ecatalog_price',
            'unit_of_measure',
            'is_active',
            'principal_name',
        ];
    }

    public function map($row): array
    {
        return [
            $row->name,
            $row->category,
            $row->description,
            $row->internal_code,
            $row->unit_price,
            $row->ecatalog_price,
            $row->unit_of_measure,
            $row->is_active,
            $row->principal_name,
        ];
    }

    public function title(): string
    {
        return 'Products';
    }
}
