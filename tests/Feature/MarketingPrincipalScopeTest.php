<?php

use App\Filament\Resources\Principals\PrincipalResource;
use App\Models\Department;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Principal;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Policies\PrincipalPolicy;
use App\Services\PrincipalScopeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

it('matches leads by assigned principal and matches nothing for an empty list', function () {
    $assigned = Principal::factory()->create();
    $other = Principal::factory()->create();
    $assignedProduct = Product::factory()->create(['principal_id' => $assigned->id]);

    $matching = Lead::factory()->create();
    $matching->leadProductItems()->create([
        'product_id' => $assignedProduct->id,
        'principal_id' => $assigned->id,
        'quantity' => 1,
        'unit_price' => 1000,
    ]);
    Lead::factory()->create();

    expect(Lead::query()->forPrincipals([$assigned->id])->pluck('id')->all())->toEqual([$matching->id])
        ->and(Lead::query()->forPrincipals([$other->id])->pluck('id')->all())->toBe([])
        ->and(Lead::query()->forPrincipals([])->count())->toBe(0)
        ->and($matching->hasAnyAssignedPrincipal([$assigned->id]))->toBeTrue()
        ->and($matching->hasAnyAssignedPrincipal([$other->id]))->toBeFalse();
});

it('matches orders by header principal and by line principal', function () {
    $assigned = Principal::factory()->create();
    $other = Principal::factory()->create();

    $headerOrder = Order::factory()->create(['principal_id' => $assigned->id]);
    $lineOrder = Order::factory()->create(['principal_id' => $other->id]);
    OrderItem::factory()->create([
        'order_id' => $lineOrder->id,
        'item_id' => Product::factory()->create(['principal_id' => $assigned->id])->id,
    ]);
    $unrelated = Order::factory()->create(['principal_id' => $other->id]);

    expect(Order::query()->forPrincipals([$assigned->id])->pluck('id')->all())->toEqualCanonicalizing([$headerOrder->id, $lineOrder->id])
        ->and(Order::query()->forPrincipals([])->count())->toBe(0)
        ->and($headerOrder->hasAnyAssignedPrincipal([$assigned->id]))->toBeTrue()
        ->and($unrelated->hasAnyAssignedPrincipal([$assigned->id]))->toBeFalse();
});

it('scopes products by principal and matches nothing for an empty list', function () {
    $assigned = Principal::factory()->create();
    $product = Product::factory()->create(['principal_id' => $assigned->id]);
    Product::factory()->create();

    expect(Product::query()->forPrincipals([$assigned->id])->pluck('id')->all())->toEqual([$product->id])
        ->and(Product::query()->forPrincipals([])->count())->toBe(0);
});

function marketingUserWithPrincipals(array $principalIds = []): User
{
    $marketing = Department::factory()->create(['name' => Department::MARKETING]);
    $user = User::factory()->create(['department_id' => $marketing->id]);

    if ($principalIds !== []) {
        $user->principals()->attach($principalIds);
    }

    return $user;
}

it('narrows a marketing user to their own and assigned-principal leads', function () {
    $principal = Principal::factory()->create();
    $user = marketingUserWithPrincipals([$principal->id]);

    $own = Lead::factory()->create(['created_by' => $user->id]);
    $shared = Lead::factory()->create(['created_by' => User::factory()->create()->id]);
    $shared->leadProductItems()->create([
        'product_id' => Product::factory()->create(['principal_id' => $principal->id])->id,
        'principal_id' => $principal->id,
        'quantity' => 1,
        'unit_price' => 1000,
    ]);
    $collaborated = Lead::factory()->create(['created_by' => User::factory()->create()->id]);
    $collaborated->collaborators()->attach($user->id, ['added_by' => $collaborated->created_by]);
    $foreign = Lead::factory()->create(['created_by' => User::factory()->create()->id]);

    $ids = PrincipalScopeService::leads(Lead::query(), $user)->pluck('id')->all();

    expect($ids)->toContain($own->id, $shared->id, $collaborated->id)->not->toContain($foreign->id);
});

it('shows only own records to a marketing user with no assignment', function () {
    $user = marketingUserWithPrincipals();

    $own = Lead::factory()->create(['created_by' => $user->id]);
    $foreign = Lead::factory()->create(['created_by' => User::factory()->create()->id]);
    $ownOrder = Order::factory()->create(['sales_id' => $user->id]);
    $foreignOrder = Order::factory()->create(['sales_id' => User::factory()->create()->id]);
    Principal::factory()->create();

    expect(PrincipalScopeService::leads(Lead::query(), $user)->pluck('id')->all())->toEqual([$own->id])
        ->and(PrincipalScopeService::orders(Order::query(), $user)->pluck('id')->all())->toEqual([$ownOrder->id])
        ->and(PrincipalScopeService::principals(Principal::query(), $user)->count())->toBe(0)
        ->and(PrincipalScopeService::products(Product::query(), $user)->count())->toBe(0)
        ->and($foreign->id)->not->toBe($own->id)
        ->and($foreignOrder->id)->not->toBe($ownOrder->id);
});

it('narrows a marketing user to assigned-principal orders', function () {
    $assigned = Principal::factory()->create();
    $other = Principal::factory()->create();
    $user = marketingUserWithPrincipals([$assigned->id]);

    $matching = Order::factory()->create(['principal_id' => $assigned->id, 'sales_id' => User::factory()->create()->id]);
    $foreign = Order::factory()->create(['principal_id' => $other->id, 'sales_id' => User::factory()->create()->id]);

    $ids = PrincipalScopeService::orders(Order::query(), $user)->pluck('id')->all();

    expect($ids)->toContain($matching->id)->not->toContain($foreign->id);
});

it('leaves non-marketing users untouched', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create(['created_by' => User::factory()->create()->id]);
    $principal = Principal::factory()->create();

    expect(PrincipalScopeService::appliesTo($user))->toBeFalse()
        ->and(PrincipalScopeService::leads(Lead::query(), $user)->pluck('id')->all())->toEqual([$lead->id])
        ->and(PrincipalScopeService::principals(Principal::query(), $user)->pluck('id')->all())->toEqual([$principal->id]);
});

it('leaves global viewers untouched even in the marketing department', function () {
    $marketing = Department::factory()->create(['name' => Department::MARKETING]);
    $user = User::factory()->create(['department_id' => $marketing->id]);
    $user->assignRole(Role::findOrCreate('Super Admin'));

    $lead = Lead::factory()->create(['created_by' => User::factory()->create()->id]);

    expect(PrincipalScopeService::appliesTo($user))->toBeFalse()
        ->and(PrincipalScopeService::leads(Lead::query(), $user)->pluck('id')->all())->toEqual([$lead->id]);
});

it('applies a raw principal list to reports without widening', function () {
    $assigned = Principal::factory()->create();
    $headerOrder = Order::factory()->create(['principal_id' => $assigned->id]);
    Order::factory()->create();

    expect(PrincipalScopeService::applyToOrders(Order::query(), null)->count())->toBe(2)
        ->and(PrincipalScopeService::applyToOrders(Order::query(), [])->count())->toBe(0)
        ->and(PrincipalScopeService::applyToOrders(Order::query(), [$assigned->id])->pluck('id')->all())->toEqual([$headerOrder->id]);
});

it('shows only assigned principals to a marketing user', function () {
    seed(RolesAndPermissionsSeeder::class);

    $marketing = Department::factory()->create(['name' => Department::MARKETING]);
    $user = User::factory()->create(['department_id' => $marketing->id]);
    $user->assignRole('Marketing Staff');

    $assigned = Principal::factory()->create();
    $other = Principal::factory()->create();
    $user->principals()->attach($assigned->id);

    $this->actingAs($user);

    expect(PrincipalScopeService::principals(Principal::query(), $user)->pluck('id')->all())->toEqual([$assigned->id])
        ->and(PrincipalResource::getEloquentQuery()->pluck('id')->all())->toEqual([$assigned->id])
        ->and(app(PrincipalPolicy::class)->view($user, $assigned))->toBeTrue()
        ->and(app(PrincipalPolicy::class)->view($user, $other))->toBeFalse();
});

it('shows every principal to a super admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Super Admin'));
    $principal = Principal::factory()->create();

    expect(app(PrincipalPolicy::class)->view($admin, $principal))->toBeTrue();
});
