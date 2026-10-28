<?php

namespace App\Imports;

use App\Models\Customer;
use App\Models\Distributor;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Principal;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

class OrdersImport implements ToCollection
{
    /**
     * @throws Exception
     */
    public function collection(Collection $collection)
    {
        // Skip header rows (index 0, 1, 2)
        $dataRows = $collection->slice(3);

        DB::transaction(function () use ($dataRows) {
            foreach ($dataRows as $row) {
                if (empty($row[1])) {
                    continue;
                }

                // Deterministic Order Number for Duplicate Detection
                // ORD-YYYY-MM-HASH(SR, Customer, Item, Qty, Total)
                $hashInput = implode('|', [
                    $row[8],  // SR
                    $row[11], // End Customer
                    $row[20], // Item Name
                    $row[21], // Qty
                    $row[23], // Total HNA
                ]);
                $orderNumber = 'ORD-'.$row[1].'-'.$row[2].'-'.strtoupper(substr(sha1($hashInput), 0, 8));

                // Check for duplicates
                if (Order::where('order_number', $orderNumber)->exists()) {
                    continue; // Skip this row
                }

                $endCustomer = Customer::where('name', $row[11])->first() ?? throw new Exception("End Customer not found: {$row[11]}");

                $principal = Principal::where('name', $row[15])->first() ?? throw new Exception("Principal not found: {$row[15]}");
                // sales_type_id is stored as string directly (e.g. INAPROC, non-INAPROC)
                $distributor = Distributor::where('name', $row[28])->first() ?? throw new Exception("Distributor not found: {$row[28]}");

                // Optional Lookups
                $lead = Lead::where('title', $row[16])->first();

                Order::create([
                    'tahun' => (int) $row[1],
                    'bulan' => (int) $row[2],
                    'department_id' => null,
                    'sales_id' => User::whereHas('position', fn ($query) => $query->where('name', $row[8]))->value('id'),
                    'end_customer_id' => $endCustomer->id,
                    'principal_id' => $principal->id,
                    'reg_inst' => $row[16] ? [$row[16]] : [],
                    'sales_type_id' => $row[17],
                    'discount_on' => (float) $row[24],
                    'net_sales_total' => (float) $row[25],
                    'jual_kso' => $row[27],
                    'distributor_id' => $distributor->id,
                    'order_number' => $orderNumber,
                    'lead_id' => $lead?->id,
                    'order_date' => now(),
                ]);
            }
        });
    }
}
