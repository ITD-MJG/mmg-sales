<?php

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Department;
use App\Models\Order;
use App\Models\Principal;
use App\Models\Role;
use App\Models\User;
use App\Policies\OrderPolicy;
use App\Services\PrincipalScopeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
    Role::findOrCreate('Marketing Staff');
});

function marketingOrderUser(array $principalIds = []): User
{
    $marketing = Department::factory()->create(['name' => Department::MARKETING]);
    $user = User::factory()->create(['department_id' => $marketing->id]);
    $user->assignRole('Marketing Staff');

    if ($principalIds !== []) {
        $user->principals()->attach($principalIds);
    }

    return $user;
}

it('scopes the order list to own, sold and assigned-principal orders', function () {
    $assigned = Principal::factory()->create();
    $user = marketingOrderUser([$assigned->id]);

    $own = Order::factory()->create(['created_by' => $user->id, 'sales_id' => User::factory()->create()->id]);
    $sold = Order::factory()->create(['sales_id' => $user->id, 'created_by' => User::factory()->create()->id]);
    $assignedOrder = Order::factory()->create([
        'principal_id' => $assigned->id,
        'created_by' => User::factory()->create()->id,
        'sales_id' => User::factory()->create()->id,
    ]);
    $foreign = Order::factory()->create([
        'principal_id' => Principal::factory()->create()->id,
        'created_by' => User::factory()->create()->id,
        'sales_id' => User::factory()->create()->id,
    ]);

    $ids = PrincipalScopeService::orders(Order::query(), $user)->pluck('id')->all();

    expect($ids)->toContain($own->id, $sold->id, $assignedOrder->id)
        ->and($ids)->not->toContain($foreign->id);
});

it('denies the policy for an out-of-scope order and allows an assigned one', function () {
    $assigned = Principal::factory()->create();
    $user = marketingOrderUser([$assigned->id]);

    $assignedOrder = Order::factory()->create(['principal_id' => $assigned->id]);
    $foreign = Order::factory()->create(['principal_id' => Principal::factory()->create()->id]);

    expect(app(OrderPolicy::class)->view($user, $foreign))->toBeFalse()
        ->and(app(OrderPolicy::class)->view($user, $assignedOrder))->toBeTrue();
});

it('resolves the order resource query through the marketing scope', function () {
    $assigned = Principal::factory()->create();
    $user = marketingOrderUser([$assigned->id]);
    $assignedOrder = Order::factory()->create(['principal_id' => $assigned->id]);
    $foreign = Order::factory()->create(['principal_id' => Principal::factory()->create()->id]);

    $this->actingAs($user);

    $ids = OrderResource::getEloquentQuery()->pluck('id')->all();

    expect($ids)->toContain($assignedOrder->id)->not->toContain($foreign->id);
});
