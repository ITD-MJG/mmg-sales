<?php

namespace App\Imports;

use App\Models\Principal;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads the business product sheet:
 * Principle | Internal Code | Principal Code | Name | UOM | Categories
 *
 * Headers may be the template's human labels or any of the snake_case spellings
 * used by earlier sheets (principle, internal_code, product_categories, ...).
 */
class ProductsImport implements ToCollection, WithHeadingRow
{
    private const CATEGORY_MAP = [
        'consumable' => 'consumables',
        'consumables' => 'consumables',
        'reagent' => 'diagnostics',
        'reagents' => 'diagnostics',
        'diagnostic' => 'diagnostics',
        'diagnostics' => 'diagnostics',
        'instrument' => 'medical_equipment',
        'instrument completeness' => 'medical_equipment',
        'medical equipment' => 'medical_equipment',
        'pharmaceutical' => 'pharmaceutical',
        'other' => 'other',
    ];

    public function collection(Collection $collection): void
    {
        foreach ($collection as $row) {
            $name = $this->firstFilled($row, ['name', 'product_name']);
            $internalCode = $this->firstFilled($row, ['internal_code']);
            $principalCode = $this->firstFilled($row, ['principal_code', 'principle_code', 'product_acc_code']);

            if ($name === null && $internalCode === null) {
                continue;
            }

            $principalId = $this->resolvePrincipalId(
                $this->firstFilled($row, ['principle', 'principal', 'principal_name'])
            );

            $product = $internalCode !== null
                ? Product::withTrashed()->firstOrNew(['internal_code' => $internalCode])
                : Product::withTrashed()->firstOrNew(['name' => $name]);

            $product->fill(array_filter([
                'name' => $name,
                'internal_code' => $internalCode,
                'product_acc_code' => $principalCode,
                'unit_of_measure' => $this->firstFilled($row, ['uom', 'unit_of_measure', 'uom_jual']),
                'category' => $this->normalizeCategory($this->firstFilled($row, ['category', 'categories', 'product_categories'])),
                'description' => $this->firstFilled($row, ['description']),
            ], fn ($value) => $value !== null));

            if ($principalId !== null) {
                $product->principal_id = $principalId;
            }

            // unit_price is NOT NULL without a default; only seed it for new rows
            // so a re-import never wipes a price someone set in the panel.
            if (! $product->exists) {
                $product->unit_price = 0;
            }

            if ($product->is_active === null) {
                $product->is_active = true;
            }

            if ($product->trashed()) {
                $product->restore();
            }

            $product->save();
        }
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function firstFilled(Collection $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    private function normalizeCategory(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = Str::lower(Str::squish(str_replace('_', ' ', $value)));

        return self::CATEGORY_MAP[$normalized] ?? 'other';
    }

    private function resolvePrincipalId(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return Principal::firstOrCreate(
            ['name' => $value],
            ['initial' => $this->generateInitial($value)]
        )->id;
    }

    private function generateInitial(string $name): string
    {
        $base = Str::upper(Str::substr($name, 0, 3));
        $initial = $base;
        $counter = 1;

        while (Principal::where('initial', $initial)->exists()) {
            $initial = $base.$counter;
            $counter++;
        }

        return $initial;
    }
}
