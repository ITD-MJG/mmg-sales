<?php

namespace Tests\Feature;

use App\Imports\OrdersImport;
use App\Jobs\ImportOrdersJob;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Distributor;
use App\Models\Order;
use App\Models\Position;
use App\Models\Principal;
use App\Models\Product;
use App\Models\Segment;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

test('orders import parses valid data correctly', function () {
    // 1. Setup Master Data
    $dept = Department::create(['name' => 'MMG', 'code' => 'DEPT01']);
    $head = Position::create(['name' => 'DONNY', 'code' => 'P01', 'level' => 1, 'department_id' => $dept->id]);
    $pm = Position::create(['name' => 'Soni_PM', 'code' => 'P02', 'level' => 2, 'department_id' => $dept->id]);
    $rsm = Position::create(['name' => 'BARAT - Rendra', 'code' => 'P03', 'level' => 3, 'department_id' => $dept->id]);
    $spv = Position::create(['name' => 'SPV_Barat by RSM Barat', 'code' => 'P04', 'level' => 4, 'department_id' => $dept->id]);
    $sr = Position::create(['name' => 'SUMBAGUT_ARI', 'code' => 'P05', 'level' => 5, 'department_id' => $dept->id]);

    $territory = Territory::create(['name' => 'BATAM', 'type' => 'city', 'level' => 3]);
    $customer = Customer::create(['name' => 'PT. Batam Karya Husada']);
    $principal = Principal::create(['name' => 'Abbott', 'initial' => 'ABB']);
    $segment = Segment::create(['name' => 'UNIVERSITY', 'code' => 'UNIV']);
    $distributor = Distributor::create(['name' => 'MJG', 'code' => 'MJG']);

    $product = Product::create([
        'name' => '9H48.02 - EMERALD DILUENT',
        'principal_id' => $principal->id,
        'unit_price' => 10000000,
        'internal_code' => 'ABB-TD-00303',
    ]);

    // 2. Mock Excel Data (Using index based on tinker dump)
    $data = [
        [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
        [null, 'Update: 181225', null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
        [null, 'Tahun', 'Bulan', 'DEPT MMG', 'HEAD', 'PM/JPM/PE', 'RSM/ASM', 'SPV', 'SR', 'AREA/KOTA', 'Customer Name', 'End Customer', 'Group Customer', 'CD/ N-CD', 'Segment', 'Principle Name', 'Reg/Inst', 'Sales Type', 'Principle Code', 'Internal Code', 'Item Name', 'Qty', 'HNA (Rp)', 'TOTAL HNA (Gross Sales) (Rp)', 'Discount (ON)', 'Net Sales TOTAL (Rp)', 'Segment - SUB', 'JUAL/KSO', 'DISTRIBUTOR', null],
        [null, '2025', '8', 'MMG', 'DONNY', 'Soni_PM', 'BARAT - Rendra', 'SPV_Barat by RSM Barat', 'SUMBAGUT_ARI', 'BATAM', 'PT. Batam Karya Husada', 'PT. Batam Karya Husada', 'PT. Batam Karya Husada', 'CLINICAL', 'UNIVERSITY', 'Abbott', 'Reg', 'E-Catalog', '9H48.02', 'ABB-TD-00303', '9H48.02 - EMERALD DILUENT', '2', '10000000', '20000000', '0.05', '19000000', 'SUPPLIER', 'JUAL', 'MJG', null],
    ];

    // 3. Run Import
    $import = new OrdersImport;
    $import->collection(collect($data));

    // 4. Assertions
    expect(Order::count())->toBe(1);
    $order = Order::first();
    expect($order->tahun)->toBe(2025);
    expect($order->bulan)->toBe(8);
    expect($order->department_id)->toBe($dept->id);
});

test('orders import fails atomically if master data is missing', function () {
    // 1. Setup minimal Master Data (Missing Department 'MMG')
    Principal::create(['name' => 'Abbott', 'initial' => 'ABB']);

    $data = [
        [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
        [null, 'Update: 181225', null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
        [null, 'Tahun', 'Bulan', 'DEPT MMG', 'HEAD', 'PM/JPM/PE', 'RSM/ASM', 'SPV', 'SR', 'AREA/KOTA', 'Customer Name', 'End Customer', 'Group Customer', 'CD/ N-CD', 'Segment', 'Principle Name', 'Reg/Inst', 'Sales Type', 'Principle Code', 'Internal Code', 'Item Name', 'Qty', 'HNA (Rp)', 'TOTAL HNA (Gross Sales) (Rp)', 'Discount (ON)', 'Net Sales TOTAL (Rp)', 'Segment - SUB', 'JUAL/KSO', 'DISTRIBUTOR', null],
        [null, '2025', '8', 'NON_EXISTENT_DEPT', 'DONNY', 'Soni_PM', 'BARAT - Rendra', 'SPV_Barat by RSM Barat', 'SUMBAGUT_ARI', 'BATAM', 'PT. Batam Karya Husada', 'PT. Batam Karya Husada', 'PT. Batam Karya Husada', 'CLINICAL', 'UNIVERSITY', 'Abbott', 'Reg', 'E-Catalog', '9H48.02', 'ABB-TD-00303', '9H48.02 - EMERALD DILUENT', '2', '10000000', '20000000', '0.05', '19000000', 'SUPPLIER', 'JUAL', 'MJG', null],
    ];

    // 2. Run Import & Expect Exception
    $import = new OrdersImport;

    try {
        $import->collection(collect($data));
    } catch (\Exception $e) {
        // Expected
    }

    // 3. Assertions: No orders created
    expect(Order::count())->toBe(0);
});

test('orders import skips duplicates correctly', function () {
    // 1. Setup Master Data
    $dept = Department::create(['name' => 'MMG', 'code' => 'DEPT01']);
    $head = Position::create(['name' => 'DONNY', 'code' => 'P01', 'level' => 1, 'department_id' => $dept->id]);
    $pm = Position::create(['name' => 'Soni_PM', 'code' => 'P02', 'level' => 2, 'department_id' => $dept->id]);
    $rsm = Position::create(['name' => 'BARAT - Rendra', 'code' => 'P03', 'level' => 3, 'department_id' => $dept->id]);
    $spv = Position::create(['name' => 'SPV_Barat by RSM Barat', 'code' => 'P04', 'level' => 4, 'department_id' => $dept->id]);
    $sr = Position::create(['name' => 'SUMBAGUT_ARI', 'code' => 'P05', 'level' => 5, 'department_id' => $dept->id]);

    $territory = Territory::create(['name' => 'BATAM', 'type' => 'city', 'level' => 3]);
    $customer = Customer::create(['name' => 'PT. Batam Karya Husada']);
    $principal = Principal::create(['name' => 'Abbott', 'initial' => 'ABB']);
    $segment = Segment::create(['name' => 'UNIVERSITY', 'code' => 'UNIV']);
    $distributor = Distributor::create(['name' => 'MJG', 'code' => 'MJG']);

    $product = Product::create([
        'name' => '9H48.02 - EMERALD DILUENT',
        'principal_id' => $principal->id,
        'unit_price' => 10000000,
        'internal_code' => 'ABB-TD-00303',
    ]);

    $data = [
        [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
        [null, 'Update: 181225', null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
        [null, 'Tahun', 'Bulan', 'DEPT MMG', 'HEAD', 'PM/JPM/PE', 'RSM/ASM', 'SPV', 'SR', 'AREA/KOTA', 'Customer Name', 'End Customer', 'Group Customer', 'CD/ N-CD', 'Segment', 'Principle Name', 'Reg/Inst', 'Sales Type', 'Principle Code', 'Internal Code', 'Item Name', 'Qty', 'HNA (Rp)', 'TOTAL HNA (Gross Sales) (Rp)', 'Discount (ON)', 'Net Sales TOTAL (Rp)', 'Segment - SUB', 'JUAL/KSO', 'DISTRIBUTOR', null],
        [null, '2025', '8', 'MMG', 'DONNY', 'Soni_PM', 'BARAT - Rendra', 'SPV_Barat by RSM Barat', 'SUMBAGUT_ARI', 'BATAM', 'PT. Batam Karya Husada', 'PT. Batam Karya Husada', 'PT. Batam Karya Husada', 'CLINICAL', 'UNIVERSITY', 'Abbott', 'Reg', 'E-Catalog', '9H48.02', 'ABB-TD-00303', '9H48.02 - EMERALD DILUENT', '2', '10000000', '20000000', '0.05', '19000000', 'SUPPLIER', 'JUAL', 'MJG', null],
        [null, '2025', '8', 'MMG', 'DONNY', 'Soni_PM', 'BARAT - Rendra', 'SPV_Barat by RSM Barat', 'SUMBAGUT_ARI', 'BATAM', 'PT. Batam Karya Husada', 'PT. Batam Karya Husada', 'PT. Batam Karya Husada', 'CLINICAL', 'UNIVERSITY', 'Abbott', 'Reg', 'E-Catalog', '9H48.02', 'ABB-TD-00303', '9H48.02 - EMERALD DILUENT', '2', '10000000', '20000000', '0.05', '19000000', 'SUPPLIER', 'JUAL', 'MJG', null], // Duplicate row
    ];

    // 2. Run Import
    $import = new OrdersImport;
    $import->collection(collect($data));

    // 3. Assertions: Only 1 order created despite 2 identical rows
    expect(Order::count())->toBe(1);
});

test('ImportOrdersJob dispatches and sends notification', function () {
    // 1. Setup
    $user = User::factory()->create();
    $dept = Department::create(['name' => 'MMG', 'code' => 'MMG']);
    Position::create(['name' => 'DONNY', 'code' => 'HEAD', 'level' => 1, 'department_id' => $dept->id]);
    Position::create(['name' => 'Soni_PM', 'code' => 'PM', 'level' => 2, 'department_id' => $dept->id]);
    Position::create(['name' => 'BARAT - Rendra', 'code' => 'RSM', 'level' => 3, 'department_id' => $dept->id]);
    Position::create(['name' => 'SPV_Barat by RSM Barat', 'code' => 'SPV', 'level' => 4, 'department_id' => $dept->id]);
    Position::create(['name' => 'SUMBAGUT_ARI', 'code' => 'SR', 'level' => 5, 'department_id' => $dept->id]);
    Territory::create(['name' => 'BATAM', 'type' => 'city', 'level' => 3]);
    Customer::create(['name' => 'PT. Batam Karya Husada']);
    Principal::create(['name' => 'Abbott', 'initial' => 'ABB']);
    Segment::create(['name' => 'UNIVERSITY', 'code' => 'UNIV']);
    Product::create(['name' => '9H48.02 - EMERALD DILUENT', 'principal_id' => Principal::where('name', 'Abbott')->first()->id, 'unit_price' => 10000000, 'internal_code' => 'ABB-TD-00303']);
    Distributor::create(['name' => 'MJG', 'code' => 'MJG']);

    $data = [
        [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
        [null, 'Update: 181225', null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
        [null, 'Tahun', 'Bulan', 'DEPT MMG', 'HEAD', 'PM/JPM/PE', 'RSM/ASM', 'SPV', 'SR', 'AREA/KOTA', 'Customer Name', 'End Customer', 'Group Customer', 'CD/ N-CD', 'Segment', 'Principle Name', 'Reg/Inst', 'Sales Type', 'Principle Code', 'Internal Code', 'Item Name', 'Qty', 'HNA (Rp)', 'TOTAL HNA (Gross Sales) (Rp)', 'Discount (ON)', 'Net Sales TOTAL (Rp)', 'Segment - SUB', 'JUAL/KSO', 'DISTRIBUTOR', null],
        [null, '2025', '8', 'MMG', 'DONNY', 'Soni_PM', 'BARAT - Rendra', 'SPV_Barat by RSM Barat', 'SUMBAGUT_ARI', 'BATAM', 'PT. Batam Karya Husada', 'PT. Batam Karya Husada', 'PT. Batam Karya Husada', 'CLINICAL', 'UNIVERSITY', 'Abbott', 'Reg', 'E-Catalog', '9H48.02', 'ABB-TD-00303', '9H48.02 - EMERALD DILUENT', '2', '10000000', '20000000', '0.05', '19000000', 'SUPPLIER', 'JUAL', 'MJG', null],
    ];

    // Create a temporary file
    $tempFileName = 'import_test_'.uniqid().'.xlsx';
    Excel::store(new class($data) implements FromCollection
    {
        public function __construct(public $data) {}

        public function collection()
        {
            return collect($this->data);
        }
    }, $tempFileName, 'public');
    $fullPath = storage_path('app/public/'.$tempFileName);

    // 2. Dispatch Job
    $job = new ImportOrdersJob($fullPath, $user->id);
    $job->handle();

    // 3. Assertions
    expect(Order::count())->toBe(1);

    if (file_exists($fullPath)) {
        unlink($fullPath);
    }
});
