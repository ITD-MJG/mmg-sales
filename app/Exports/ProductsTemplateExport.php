<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductsTemplateExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public const HEADINGS = [
        'Principle',
        'Internal Code',
        'Principal Code',
        'Name',
        'UOM',
        'Categories',
    ];

    public function collection(): Collection
    {
        return collect([
            [
                'PT Example Principal',
                'MSS-TD-00001',
                'P706406P',
                'Petrifilm AC Plate',
                'BOX',
                'Consumables',
            ],
        ]);
    }

    public function headings(): array
    {
        return self::HEADINGS;
    }
}
