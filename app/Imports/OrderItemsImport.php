<?php

namespace App\Imports;

use App\Models\Principal;
use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class OrderItemsImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array{row: int, reason: string}> */
    private array $failures = [];

    /** @var array<int, array<string, int|float|string>> */
    private array $items = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $data = $row instanceof Collection ? $row->toArray() : (array) $row;
            $rowNumber = $index + 2;

            if (collect($data)->every(fn ($value): bool => blank($value))) {
                continue;
            }

            $identifiers = array_filter([
                'internal_code' => $data['internal_code'] ?? null,
                'product_acc_code' => $data['principle_code'] ?? null,
                'name' => $data['item_description'] ?? null,
            ], fn ($value): bool => filled($value));

            if ($identifiers === []) {
                $this->failures[] = ['row' => $rowNumber, 'reason' => 'No product identifier was provided.'];
                continue;
            }

            $principalName = trim((string) ($data['principle'] ?? $data['principal'] ?? ''));
            $principal = $principalName === '' ? null : Principal::query()->where('name', $principalName)->first();

            if ($principalName !== '' && ! $principal) {
                $this->failures[] = ['row' => $rowNumber, 'reason' => 'Principal is unknown.'];
                continue;
            }

            $productQuery = Product::query()
                ->when($principal, fn ($query) => $query->where('principal_id', $principal->id))
                ->where(function ($query) use ($identifiers): void {
                    foreach ($identifiers as $column => $value) {
                        $query->orWhere($column, trim((string) $value));
                    }
                });

            $products = $productQuery->get();

            if ($products->count() !== 1) {
                $reason = $products->isEmpty()
                    ? 'Product not found for this principal.'
                    : 'Product identifiers match multiple products.';
                $this->failures[] = ['row' => $rowNumber, 'reason' => $reason];
                continue;
            }

            $product = $products->first();
            $principal ??= $product->principal;

            $rawQuantity = trim((string) ($data['qty'] ?? ''));
            $quantity = is_numeric($rawQuantity) && (float) $rawQuantity === (float) (int) $rawQuantity
                ? (int) $rawQuantity
                : false;
            $unitPrice = $data['unit_price'] ?? null;

            if ($quantity === false || $quantity < 1 || ! is_numeric($unitPrice) || (float) $unitPrice < 0) {
                $this->failures[] = ['row' => $rowNumber, 'reason' => 'Quantity or unit price is missing or invalid.'];
                continue;
            }

            $unitPrice = (float) $unitPrice;
            $this->items[] = [
                'principal_id' => $principal->id,
                'item_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'current_price' => $unitPrice,
                'price_type' => 'unit_price',
                'discount_amount' => 0,
                'subtotal' => round($quantity * $unitPrice, 2),
            ];
        }
    }

    /** @return array<int, array<string, int|float|string>> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return array<int, array{row: int, reason: string}> */
    public function failures(): array
    {
        return $this->failures;
    }
}
