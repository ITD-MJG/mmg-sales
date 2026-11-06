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

it('saves an annual target as a single row with no month', function () {
    livewire(ManageTargets::class)
        ->callAction('create', [
            'scope' => 'annual',
            'user_id' => $this->user->id,
            'year' => 2026,
            'annual_target' => 120_000_000,
        ])
        ->assertHasNoFormErrors();

    $target = Target::query()->sole();

    expect($target->user_id)->toBe($this->user->id)
        ->and($target->year)->toBe(2026)
        ->and($target->month)->toBeNull()
        ->and((float) $target->annual_target)->toBe(120_000_000.0)
        ->and($target->monthly_target)->toBeNull();
});

it('saves a specific month target without an annual amount', function () {
    livewire(ManageTargets::class)
        ->callAction('create', [
            'scope' => 'month',
            'user_id' => $this->user->id,
            'year' => 2026,
            'month' => 3,
            'monthly_target' => 10_000_000,
        ])
        ->assertHasNoFormErrors();

    $target = Target::query()->sole();

    expect($target->month)->toBe(3)
        ->and((float) $target->monthly_target)->toBe(10_000_000.0)
        ->and($target->annual_target)->toBeNull();
});

it('rejects a second annual target for the same representative and year', function () {
    Target::create([
        'user_id' => $this->user->id,
        'year' => 2026,
        'month' => null,
        'annual_target' => 100_000_000,
    ]);

    livewire(ManageTargets::class)
        ->callAction('create', [
            'scope' => 'annual',
            'user_id' => $this->user->id,
            'year' => 2026,
            'annual_target' => 120_000_000,
        ])
        ->assertHasFormErrors(['user_id' => 'unique']);

    expect(Target::query()->count())->toBe(1)
        ->and((float) Target::query()->sole()->annual_target)->toBe(100_000_000.0);
});

it('allows an annual target for a different year', function () {
    Target::create([
        'user_id' => $this->user->id,
        'year' => 2025,
        'month' => null,
        'annual_target' => 100_000_000,
    ]);

    livewire(ManageTargets::class)
        ->callAction('create', [
            'scope' => 'annual',
            'user_id' => $this->user->id,
            'year' => 2026,
            'annual_target' => 120_000_000,
        ])
        ->assertHasNoFormErrors();

    expect(Target::query()->count())->toBe(2);
});

it('rejects a duplicate month target', function () {
    Target::create([
        'user_id' => $this->user->id,
        'year' => 2026,
        'month' => 3,
        'monthly_target' => 10_000_000,
    ]);

    livewire(ManageTargets::class)
        ->callAction('create', [
            'scope' => 'month',
            'user_id' => $this->user->id,
            'year' => 2026,
            'month' => 3,
            'monthly_target' => 20_000_000,
        ])
        ->assertHasFormErrors(['user_id' => 'unique']);

    expect(Target::query()->count())->toBe(1);
});

it('keeps monthly rows from inflating the annual total', function () {
    livewire(ManageTargets::class)->callAction('create', [
        'scope' => 'annual',
        'user_id' => $this->user->id,
        'year' => 2026,
        'annual_target' => 120_000_000,
    ]);

    foreach ([1, 2] as $month) {
        livewire(ManageTargets::class)->callAction('create', [
            'scope' => 'month',
            'user_id' => $this->user->id,
            'year' => 2026,
            'month' => $month,
            'monthly_target' => 10_000_000,
        ]);
    }

    expect((float) Target::query()->sum('annual_target'))->toBe(120_000_000.0)
        ->and((float) Target::query()->sum('monthly_target'))->toBe(20_000_000.0);
});

it('requires the amount that matches the chosen target type', function () {
    livewire(ManageTargets::class)
        ->callAction('create', [
            'scope' => 'month',
            'user_id' => $this->user->id,
            'year' => 2026,
            'month' => 4,
        ])
        ->assertHasFormErrors(['monthly_target' => 'required']);

    livewire(ManageTargets::class)
        ->callAction('create', [
            'scope' => 'annual',
            'user_id' => $this->user->id,
            'year' => 2027,
        ])
        ->assertHasFormErrors(['annual_target' => 'required']);

    expect(Target::query()->count())->toBe(0);
});

it('blanks the annual amount when a month target is submitted after switching type', function () {
    livewire(ManageTargets::class)
        ->callAction('create', [
            'scope' => 'month',
            'user_id' => $this->user->id,
            'year' => 2026,
            'month' => 5,
            'monthly_target' => 10_000_000,
            'annual_target' => 999_000_000,
        ])
        ->assertHasNoFormErrors();

    $target = Target::query()->sole();

    expect($target->annual_target)->toBeNull()
        ->and((float) $target->monthly_target)->toBe(10_000_000.0);
});

it('blanks the month and monthly amount for an annual target', function () {
    livewire(ManageTargets::class)
        ->callAction('create', [
            'scope' => 'annual',
            'user_id' => $this->user->id,
            'year' => 2026,
            'annual_target' => 120_000_000,
            'month' => 7,
            'monthly_target' => 9_000_000,
        ])
        ->assertHasNoFormErrors();

    $target = Target::query()->sole();

    expect($target->month)->toBeNull()
        ->and($target->monthly_target)->toBeNull()
        ->and((float) $target->annual_target)->toBe(120_000_000.0);
});
