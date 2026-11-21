<?php

namespace Tests\Feature\Filament\Orders;

use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Models\Lead;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    actingAs($admin);
});

it('rehydrates the lead selector from a saved lead_id', function () {
    $lead = Lead::factory()->create(['status' => 'prospect']);
    $order = Order::factory()->create(['lead_id' => $lead->id, 'opportunity_id' => null]);

    $data = invade(new EditOrder)->mutateFormDataBeforeFill([
        'lead_id' => $order->lead_id,
        'order_source' => 'manual',
    ]);

    expect($data['order_source'])->toBe('leads')
        ->and($data['source_record_id'])->toBe((string) $lead->id);
});

it('leaves the form alone when the order has no lead', function () {
    $order = Order::factory()->create(['lead_id' => null, 'opportunity_id' => null]);

    $data = invade(new EditOrder)->mutateFormDataBeforeFill([
        'lead_id' => null,
        'order_source' => 'manual',
    ]);

    expect($data['order_source'])->toBe('manual')
        ->and($data)->not->toHaveKey('source_record_id');
});

it('keeps the lead_id when the order is saved again unchanged', function () {
    $lead = Lead::factory()->create(['status' => 'prospect']);
    $order = Order::factory()->create(['lead_id' => $lead->id, 'opportunity_id' => null]);

    $order->update(['notes' => 'edited note']);

    expect($order->fresh()->lead_id)->toBe($lead->id);
});
