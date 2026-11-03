<?php

namespace Tests\Feature;

use App\Exports\ProductsDataExport;
use App\Exports\ProductsTemplateExport;
use App\Imports\ProductsImport;
use App\Models\Principal;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

uses(RefreshDatabase::class);

afterEach(function () {
    HeadingRowFormatter::reset();
});

function importProductsSheet(array $rows): string
{
    $name = 'products-import-'.uniqid().'.xlsx';

    Excel::store(new class($rows) implements FromArray
    {
        public function __construct(private array $rows) {}

        public function array(): array
        {
            return $this->rows;
        }
    }, $name, 'local');

    Excel::import(new ProductsImport, storage_path('app/private/'.$name));

    return $name;
}

it('exports the template with the requested headings', function () {
    expect((new ProductsTemplateExport)->headings())
        ->toBe(['Principle', 'Internal Code', 'Principal Code', 'Name', 'UOM', 'Categories']);
});

it('exports product data under the same headings in the same order', function () {
    $principal = Principal::factory()->create(['name' => 'PT Example Principal']);

    Product::factory()->create([
        'name' => 'Petrifilm AC Plate',
        'internal_code' => 'MSS-TD-00001',
        'product_acc_code' => 'P706406P',
        'unit_of_measure' => 'BOX',
        'category' => 'consumables',
        'principal_id' => $principal->id,
    ]);

    $export = new ProductsDataExport;
    $row = $export->map($export->collection()->first());

    expect($export->headings())->toBe((new ProductsTemplateExport)->headings())
        ->and($row)->toBe(['PT Example Principal', 'MSS-TD-00001', 'P706406P', 'Petrifilm AC Plate', 'BOX', 'consumables']);
});

it('imports a sheet using the business headers', function () {
    importProductsSheet([
        ['PRINCIPLE', 'INTERNAL CODE', 'PRINCIPLE CODE', 'Name', 'UOM', 'PRODUCT CATEGORIES'],
        ['PT. MEGAH SEJAHTERA', 'MSS-TD-00003', 'P706406P', 'Petrifilm AC Plate', 'BOX', 'CONSUMABLE'],
    ]);

    $product = Product::where('internal_code', 'MSS-TD-00003')->first();

    expect($product)->not->toBeNull()
        ->and($product->product_acc_code)->toBe('P706406P')
        ->and($product->name)->toBe('Petrifilm AC Plate')
        ->and($product->unit_of_measure)->toBe('BOX')
        ->and($product->category)->toBe('consumables')
        ->and($product->principal->name)->toBe('PT. MEGAH SEJAHTERA');
});

it('imports a sheet using the template headers', function () {
    importProductsSheet([
        ['Principle', 'Internal Code', 'Principal Code', 'Name', 'UOM', 'Categories'],
        ['PT Example Principal', 'INT-0001', 'PRN-0001', 'Example Item', 'PACK', 'Reagent'],
    ]);

    $product = Product::where('internal_code', 'INT-0001')->first();

    expect($product->product_acc_code)->toBe('PRN-0001')
        ->and($product->category)->toBe('diagnostics');
});

it('updates an existing product matched by internal code instead of duplicating it', function () {
    $product = Product::factory()->create([
        'name' => 'Old Name',
        'internal_code' => 'MSS-TD-00009',
        'product_acc_code' => null,
    ]);

    importProductsSheet([
        ['Principle', 'Internal Code', 'Principal Code', 'Name', 'UOM', 'Categories'],
        ['PT Example Principal', 'MSS-TD-00009', 'NEW-CODE', 'New Name', 'BOX', 'Consumables'],
    ]);

    expect(Product::where('internal_code', 'MSS-TD-00009')->count())->toBe(1);

    $product->refresh();
    expect($product->product_acc_code)->toBe('NEW-CODE')
        ->and($product->name)->toBe('New Name');
});

it('maps unknown categories to other and keeps unmapped columns untouched', function () {
    $product = Product::factory()->create([
        'internal_code' => 'INT-KEEP',
        'unit_price' => 123456,
        'description' => 'Existing description',
    ]);

    importProductsSheet([
        ['Principle', 'Internal Code', 'Principal Code', 'Name', 'UOM', 'Categories'],
        ['PT Example Principal', 'INT-KEEP', 'PC-1', 'Renamed', 'BOX', 'SOMETHING NEW'],
    ]);

    $product->refresh();
    expect($product->category)->toBe('other')
        ->and($product->unit_price)->toBe(123456)
        ->and($product->description)->toBe('Existing description');
});

it('restores a soft-deleted product matched by internal code', function () {
    $product = Product::factory()->create(['internal_code' => 'INT-DELETED']);
    $product->delete();

    importProductsSheet([
        ['Principle', 'Internal Code', 'Principal Code', 'Name', 'UOM', 'Categories'],
        ['PT Example Principal', 'INT-DELETED', 'PC-2', 'Restored', 'BOX', 'Consumables'],
    ]);

    expect(Product::where('internal_code', 'INT-DELETED')->exists())->toBeTrue();
});
