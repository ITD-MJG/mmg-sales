<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Department;
use App\Models\Distributor;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Position;
use App\Models\Principal;
use App\Models\Product;
use App\Models\Segment;
use App\Models\SubSegment;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SampleSalesDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PrincipalSeeder::class,
            ProductSeeder::class,
        ]);

        $dept = Department::where('code', 'SALES')->first() ?? Department::first();
        $headPos = Position::where('code', 'HEAD')->first() ?? Position::first();
        $rsmPos = Position::where('code', 'RSM')->first() ?? Position::first();
        $asmPos = Position::where('code', 'ASM')->first() ?? Position::first();
        $spvPos = Position::where('code', 'SPV')->first() ?? Position::first();
        $srPos = Position::where('code', 'SR')->first() ?? Position::first();
        $user = User::first();

        $cities = Territory::where('type', 'city')->limit(5)->get();
        if ($cities->isEmpty()) {
            $cities = collect([Territory::create(['name' => 'Sample City', 'type' => 'city', 'level' => 3])]);
        }

        $govGroup = CustomerGroup::updateOrCreate(['code' => 'GOV'], ['name' => 'Government', 'is_active' => true]);
        $privGroup = CustomerGroup::updateOrCreate(['code' => 'PRIV'], ['name' => 'Private', 'is_active' => true]);

        $pharmaSegment = Segment::updateOrCreate(['code' => 'PHARMA'], ['name' => 'Pharmaceuticals']);
        $medEquipSegment = Segment::updateOrCreate(['code' => 'MEDEQ'], ['name' => 'Medical Equipment']);

        $genSubSegment = SubSegment::updateOrCreate(['code' => 'GEN'], ['name' => 'Generic', 'segment_id' => $pharmaSegment->id]);
        $dispSubSegment = SubSegment::updateOrCreate(['code' => 'DISP'], ['name' => 'Disposable', 'segment_id' => $medEquipSegment->id]);

        $distributor = Distributor::updateOrCreate(
            ['code' => 'DIST01'],
            ['name' => 'Main Distributor Jakarta', 'city' => 'Jakarta', 'is_active' => true]
        );

        $principalIds = Principal::pluck('id')->toArray();
        $productIds = Product::pluck('id')->toArray();

        $customersData = [
            ['email' => 'rs_central@example.com', 'name' => 'RS Central Jakarta', 'type' => 'hospital_clinic', 'class' => 'tier_1'],
            ['email' => 'klinik_bunda@example.com', 'name' => 'Klinik Bunda', 'type' => 'hospital_clinic', 'class' => 'tier_2'],
            ['email' => 'apotek_jaya@example.com', 'name' => 'Apotek Jaya', 'type' => 'hospital_clinic', 'class' => 'tier_3'],
            ['email' => 'pt_sehat@example.com', 'name' => 'PT Sehat Sentosa', 'type' => 'pt_cv', 'class' => 'tier_2'],
        ];

        foreach ($customersData as $c) {
            Customer::updateOrCreate(
                ['email' => $c['email']],
                [
                    'name' => $c['name'],
                    'type' => $c['type'],
                    'classification' => $c['class'],
                    'address' => 'Jl. Sample No. '.rand(1, 100),
                    'city' => 'Jakarta',
                    'is_active' => true,
                    'assigned_to' => $user?->id,
                ]
            );
        }
        $customerIds = Customer::pluck('id')->toArray();

        $leadStatuses = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost'];
        $leadSources = ['website', 'referral', 'cold_call', 'trade_show', 'partner', 'other'];
        $leadPriorities = ['low', 'medium', 'high', 'urgent'];

        for ($i = 1; $i <= 15; $i++) {
            $lead = Lead::create([
                'customer_name' => 'Prospect '.Str::random(5),
                'contact_person' => $customerIds ? Contact::where('customer_id', $customerIds[array_rand($customerIds)])->value('id') : null,
                'email' => 'contact'.$i.'@prospect.com',
                'phone' => '0812'.rand(10000000, 99999999),
                'status' => $leadStatuses[array_rand($leadStatuses)],
                'source' => $leadSources[array_rand($leadSources)],
                'priority' => $leadPriorities[array_rand($leadPriorities)],
                'estimated_value' => rand(5000000, 100000000),
                'customer_id' => $i % 2 == 0 ? $customerIds[array_rand($customerIds)] : null,
                'assigned_to' => $user?->id,
                'created_by' => $user?->id,
            ]);

            $lead->collaborators()->attach($user?->id, ['added_by' => $user?->id]);
        }

        $orderStatuses = ['draft', 'pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'];

        for ($i = 1; $i <= 20; $i++) {
            $selectedProduct = Product::find($productIds[array_rand($productIds)]);
            $qty = rand(5, 100);
            $totalGross = $selectedProduct->unit_price * $qty;
            $discount = rand(0, 15);
            $netTotal = $totalGross * (1 - ($discount / 100));

            Order::create([
                'tahun' => 2025,
                'bulan' => rand(1, 3),
                'department_id' => $dept->id,
                'head_position_id' => $headPos->id,
                'rsm_asm_position_id' => $rsmPos->id,
                'spv_position_id' => $spvPos->id,
                'sales' => [$user?->id ?? User::factory()->create()->id],
                'end_customer_id' => $customerIds[array_rand($customerIds)],
                'principal_id' => $selectedProduct->principal_id,
                'reg_inst' => collect(['REG', 'INST', 'Consumable'])->random(rand(1, 2))->values()->all(),
                'sales_type_id' => rand(0, 1) ? 'INAPROC' : 'non-INAPROC',
                'net_sales_total' => $netTotal,
                'jual_kso' => rand(0, 1) ? 'Jual' : 'KSO',
                'distributor_id' => $distributor->id,
                'order_number' => 'MMG-ORD-2025-'.Str::padLeft($i + 5, 8, '0'),
                'status' => $orderStatuses[array_rand($orderStatuses)],
                'subtotal' => $totalGross,
                'total_amount' => $netTotal,
                'order_date' => now()->subDays(rand(1, 60)),
                'created_by' => $user?->id,
            ]);
        }
    }
}
