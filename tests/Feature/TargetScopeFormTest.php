<?php

use App\Filament\Resources\Targets\Pages\ManageTargets;
use App\Models\Target;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\seed;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('Sales Manager');

    $this->actingAs($this->user);
});

it('creates a monthly target without an annual amount', function () {
    livewire(ManageTargets::class)
        ->callAction('create', [
            'user_id' => $this->user->id,
            'year' => 2026,
            'month' => 3,
            'monthly_target' => 10_000_000,
        ])
        ->assertHasNoFormErrors();

    $target = Target::query()->sole();

    expect($target->month)->toBe(3)
        ->and((float) $target->monthly_target)->toBe(10_000_000.0)
        ->and($target->actual)->toBeNull();
});

it('requires a month and a target amount', function () {
    livewire(ManageTargets::class)
        ->callAction('create', [
            'user_id' => $this->user->id,
            'year' => 2026,
        ])
        ->assertHasFormErrors(['month' => 'required', 'monthly_target' => 'required']);

    expect(Target::query()->count())->toBe(0);
});

it('rejects a duplicate target for the same representative and month', function () {
    Target::create([
        'user_id' => $this->user->id,
        'year' => 2026,
        'month' => 3,
        'monthly_target' => 10_000_000,
    ]);

    livewire(ManageTargets::class)
        ->callAction('create', [
            'user_id' => $this->user->id,
            'year' => 2026,
            'month' => 3,
            'monthly_target' => 20_000_000,
        ])
        ->assertHasFormErrors(['user_id' => 'unique']);

    expect(Target::query()->count())->toBe(1)
        ->and((float) Target::query()->sole()->monthly_target)->toBe(10_000_000.0);
});

it('allows the same month for a different year', function () {
    Target::create([
        'user_id' => $this->user->id,
        'year' => 2025,
        'month' => 3,
        'monthly_target' => 10_000_000,
    ]);

    livewire(ManageTargets::class)
        ->callAction('create', [
            'user_id' => $this->user->id,
            'year' => 2026,
            'month' => 3,
            'monthly_target' => 20_000_000,
        ])
        ->assertHasNoFormErrors();

    expect(Target::query()->count())->toBe(2);
});

it('does not offer the actual on create', function () {
    livewire(ManageTargets::class)
        ->mountAction('create')
        ->assertFormFieldHidden('actual');
});

it('reports the actual on an existing target', function () {
    $target = Target::create([
        'user_id' => $this->user->id,
        'year' => 2026,
        'month' => 3,
        'monthly_target' => 10_000_000,
    ]);

    livewire(ManageTargets::class)
        ->mountTableAction('edit', $target)
        ->fillForm(['actual' => 7_500_000])
        ->callMountedTableAction();

    expect((float) $target->refresh()->actual)->toBe(7_500_000.0)
        ->and((float) $target->monthly_target)->toBe(10_000_000.0);
});
