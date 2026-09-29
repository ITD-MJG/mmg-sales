<?php

namespace Tests\Feature\Filament\Products;

use App\Filament\Resources\Principals\Pages\EditPrincipal;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Models\Principal;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Gate::before(fn () => true);
    $user = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'admin']);

    $permissions = [
        'create_product', 'view_product', 'update_product', 'delete_product', 'view_any_product',
        'create_principal', 'view_principal', 'update_principal', 'delete_principal', 'view_any_principal',
    ];

    foreach ($permissions as $permissionName) {
        Permission::firstOrCreate(['name' => $permissionName]);
    }

    $role->givePermissionTo($permissions);
    $user->assignRole($role);

    $this->actingAs($user);
});

it('can create a product with a principal', function () {
    $principal = Principal::factory()->create();

    livewire(CreateProduct::class)
        ->set('data.name', 'Test Product')
        ->set('data.internal_code', 'TP-001')
        ->set('data.category', 'medical_equipment')
        ->set('data.unit_price', 100.00)
        ->set('data.principal_id', $principal->id)
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::where('name', 'Test Product')->first();
    expect($product->name)->toBe('Test Product');
    expect($product->principal->id)->toBe($principal->id);
});

it('can display product with associated principal in infolist', function () {
    $principal = Principal::factory()->create();
    $product = Product::factory()->create(['principal_id' => $principal->id]);

    livewire(ViewProduct::class, ['record' => $product->getKey()])
        ->assertSee($principal->name);
});

it('can edit a product to associate with a principal', function () {
    $principal1 = Principal::factory()->create();
    $principal2 = Principal::factory()->create();
    $product = Product::factory()->create(['principal_id' => $principal1->id]);

    livewire(EditProduct::class, ['record' => $product->getKey()])
        ->set('data.principal_id', $principal2->id)
        ->call('save')
        ->assertHasNoFormErrors();

    $product->refresh();
    expect($product->principal->id)->toBe($principal2->id);
});

it('can disassociate a principal from a product', function () {
    $principal = Principal::factory()->create();
    $product = Product::factory()->create(['principal_id' => $principal->id]);

    livewire(EditProduct::class, ['record' => $product->getKey()])
        ->set('data.principal_id', null)
        ->call('save')
        ->assertHasNoFormErrors();

    $product->refresh();
    expect($product->principal_id)->toBeNull();
});

// Test relationship integrity when principal is deleted (onDelete('set null'))
it('principal_id is set to null when associated principal is deleted', function () {
    $principal = Principal::factory()->create();
    $product = Product::factory()->create(['principal_id' => $principal->id]);

    expect($product->principal_id)->toBe($principal->id);

    // Using Filament's DeleteAction to delete the principal
    livewire(EditPrincipal::class, ['record' => $principal->getKey()])
        ->callAction(DeleteAction::class);

    $product->refresh();
    expect($product->principal_id)->toBeNull();
});
