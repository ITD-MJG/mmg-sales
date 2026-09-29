<?php

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Order;
use App\Models\Principal;
use App\Models\Product;
use App\Services\ResourceCodeGenerator;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('code_sequences')->truncate();
});

describe('Model Code Generation', function () {
    it('generates lead_code when creating lead', function () {
        $lead = Lead::factory()->create();

        expect($lead->lead_code)->not->toBeNull();
        expect($lead->lead_code)->toMatch('/^LEA-\d{6}$/');
    });

    it('generates activity_code when creating activity', function () {
        $activity = Activity::factory()->create();

        expect($activity->activity_code)->not->toBeNull();
        expect($activity->activity_code)->toMatch('/^ACT-\d{4}-\d{6}$/');
    });

    it('generates customer_code when creating customer', function () {
        $customer = Customer::factory()->create();

        expect($customer->customer_code)->not->toBeNull();
        expect($customer->customer_code)->toMatch('/^CST-\d{6}$/');
    });

    it('generates contact_code when creating contact', function () {
        $contact = Contact::factory()->create();

        expect($contact->contact_code)->not->toBeNull();
        expect($contact->contact_code)->toMatch('/^CON-\d{6}$/');
    });

    it('generates principal_code when creating principal', function () {
        $principal = Principal::factory()->create();

        expect($principal->principal_code)->not->toBeNull();
        expect($principal->principal_code)->toMatch('/^PRN-\d{6}$/');
    });

    it('reuses latest existing principal_code after deletion', function () {
        $principal1 = Principal::factory()->create();
        $principal2 = Principal::factory()->create();
        $principal3 = Principal::factory()->create();

        expect($principal1->principal_code)->toBe('PRN-000001');
        expect($principal2->principal_code)->toBe('PRN-000002');
        expect($principal3->principal_code)->toBe('PRN-000003');

        // Delete the last principal
        $principal3->delete();

        // Create a new principal - should get code 3 (latest existing + 1)
        $principal4 = Principal::factory()->create();
        expect($principal4->principal_code)->toBe('PRN-000003');

        // Delete principal 2
        $principal2->delete();

        // Create another principal - should get code 4 (latest existing 003 + 1)
        $principal5 = Principal::factory()->create();
        expect($principal5->principal_code)->toBe('PRN-000004');
    });

    it('generates product_code when creating product', function () {
        $product = Product::factory()->create();

        expect($product->product_code)->not->toBeNull();
        expect($product->product_code)->toMatch('/^[A-Z0-9]+-TD-\d{6}$/');
    });

    it('generates sequential codes for multiple leads', function () {
        $lead1 = Lead::factory()->create();
        $lead2 = Lead::factory()->create();
        $lead3 = Lead::factory()->create();

        expect($lead1->lead_code)->toBe('LEA-000001');
        expect($lead2->lead_code)->toBe('LEA-000002');
        expect($lead3->lead_code)->toBe('LEA-000003');
    });

    it('allows manual code assignment', function () {
        $lead = Lead::factory()->create([
            'lead_code' => 'CUSTOM-001',
        ]);

        expect($lead->lead_code)->toBe('CUSTOM-001');
    });

    it('generates unique codes across different models', function () {
        $lead = Lead::factory()->create();
        $activity = Activity::factory()->create();
        $customer = Customer::factory()->create();

        expect($lead->lead_code)->toMatch('/^LEA-\d{6}$/');
        expect($activity->activity_code)->toMatch('/^ACT-\d{4}-\d{6}$/');
        expect($customer->customer_code)->toMatch('/^CST-\d{6}$/');
    });
});

describe('Order Order Number Generation', function () {
    it('generates order_number when creating order', function () {
        $order = Order::factory()->create();

        expect($order->order_number)->not->toBeNull();
        expect($order->order_number)->toMatch('/^ORD-\d{4}-\d{6}$/');
    });

    it('generates sequential order numbers', function () {
        $order1 = Order::factory()->create();
        $order2 = Order::factory()->create();
        $order3 = Order::factory()->create();

        expect($order1->order_number)->toBe('ORD-2026-000001');
        expect($order2->order_number)->toBe('ORD-2026-000002');
        expect($order3->order_number)->toBe('ORD-2026-000003');
    });

    it('resets order sequence for new year', function () {
        $order1 = Order::factory()->create();
        $order2 = Order::factory()->create();

        $order3 = Order::factory()->create(['created_at' => now()->addYear()]);

        expect($order3->order_number)->toBe('ORD-2027-000001');
    });

    it('generates order number using current year when not specified', function () {
        $order = Order::factory()->create();

        expect($order->order_number)->toMatch('/^ORD-'.date('Y').'-\d{6}$/');
    });
});

it('generates opportunity codes with the OPP prefix', function () {
    $generator = app(ResourceCodeGenerator::class);

    $code = $generator->generateForOpportunity('202609');

    expect($code)->toBe('OPP-202609-0001');
});

it('increments opportunity codes within a month', function () {
    $generator = app(ResourceCodeGenerator::class);

    Opportunity::factory()->create(['opportunity_code' => 'OPP-202609-0001']);

    expect($generator->generateForOpportunity('202609'))->toBe('OPP-202609-0002');
});
