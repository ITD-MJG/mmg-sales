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

it('creates one row per filled month', function () {
    livewire(ManageTargets::class)
        ->callAction('create', [
            'user_id' => $this->user->id,
            'year' => 2026,
            'monthly_targets' => [
                ['month' => 1, 'monthly_target' => 10_000_000],
                ['month' => 3, 'monthly_target' => 12_000_000],
                ['month' => 2],
            ],
        ])
        ->assertHasNoFormErrors();

    $targets = Target::query()->orderBy('month')->get();

    expect($targets)->toHaveCount(2)
        ->and($targets->pluck('month')->all())->toBe([1, 3])
        ->and((float) $targets[0]->monthly_target)->toBe(10_000_000.0)
        ->and((float) $targets[1]->monthly_target)->toBe(12_000_000.0)
        ->and($targets->every(fn (Target $t): bool => $t->actual === null))->toBeTrue();
});

it('requires at least one month target', function () {
    livewire(ManageTargets::class)
        ->callAction('create', [
            'user_id' => $this->user->id,
            'year' => 2026,
            'monthly_targets' => [],
        ])
        ->assertHasFormErrors(['monthly_targets']);

    expect(Target::query()->count())->toBe(0);
});

it('rejects a submission where every month is blank', function () {
    livewire(ManageTargets::class)
        ->callAction('create', [
            'user_id' => $this->user->id,
            'year' => 2026,
            'monthly_targets' => collect(range(1, 12))
                ->map(fn (int $month): array => ['month' => $month])
                ->all(),
        ])
        ->assertHasFormErrors(['monthly_targets']);

    expect(Target::query()->count())->toBe(0);
});

it('writes only the months that carry an amount', function () {
    livewire(ManageTargets::class)
        ->callAction('create', [
            'user_id' => $this->user->id,
            'year' => 2026,
            'monthly_targets' => collect(range(1, 12))
                ->map(fn (int $month): array => $month % 2 === 0
                    ? ['month' => $month, 'monthly_target' => 5_000_000]
                    : ['month' => $month])
                ->all(),
        ])
        ->assertHasNoFormErrors();

    $targets = Target::query()->orderBy('month')->get();

    expect($targets)->toHaveCount(6)
        ->and($targets->pluck('month')->all())->toBe([2, 4, 6, 8, 10, 12])
        ->and($targets->every(fn (Target $t): bool => (float) $t->monthly_target === 5_000_000.0))->toBeTrue();
});

it('writes one row when the same month arrives twice', function () {
    livewire(ManageTargets::class)
        ->callAction('create', [
            'user_id' => $this->user->id,
            'year' => 2026,
            'monthly_targets' => [
                ['month' => 5, 'monthly_target' => 5_000_000],
                ['month' => 5, 'monthly_target' => 7_000_000],
            ],
        ])
        ->assertHasNoFormErrors();

    $targets = Target::query()->orderBy('month')->get();

    expect($targets)->toHaveCount(1)
        ->and($targets->pluck('month')->all())->toBe([5])
        ->and((float) $targets[0]->monthly_target)->toBe(7_000_000.0);
});

it('rejects a negative target amount', function () {
    livewire(ManageTargets::class)
        ->callAction('create', [
            'user_id' => $this->user->id,
            'year' => 2026,
            'monthly_targets' => [['month' => 1, 'monthly_target' => -1]],
        ])
        ->assertHasFormErrors(['monthly_targets.0.monthly_target']);

    expect(Target::query()->count())->toBe(0);
});

it('rejects a month outside the calendar range', function () {
    livewire(ManageTargets::class)
        ->callAction('create', [
            'user_id' => $this->user->id,
            'year' => 2026,
            'monthly_targets' => [['month' => 13, 'monthly_target' => 10_000_000]],
        ])
        ->assertHasFormErrors(['monthly_targets.0.month']);

    expect(Target::query()->count())->toBe(0);
});

it('rejects a second create for the same representative and year', function () {
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
            'monthly_targets' => [['month' => 4, 'monthly_target' => 20_000_000]],
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
            'monthly_targets' => [['month' => 3, 'monthly_target' => 20_000_000]],
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

it('hides the repeater when editing a single month', function () {
    $target = Target::create([
        'user_id' => $this->user->id,
        'year' => 2026,
        'month' => 3,
        'monthly_target' => 10_000_000,
    ]);

    livewire(ManageTargets::class)
        ->mountTableAction('edit', $target)
        ->assertFormFieldHidden('monthly_targets');
});

it('renders twelve month rows on the create modal', function () {
    $component = livewire(ManageTargets::class)->mountAction('create');

    $state = $component->get('mountedActions.0.data.monthly_targets');

    expect($state)->toHaveCount(12)
        ->and(array_column($state, 'month'))->toBe(range(1, 12));

    $component->assertFormFieldVisible('monthly_targets')
        ->assertFormFieldHidden('month')
        ->assertFormFieldHidden('monthly_target')
        ->assertFormFieldHidden('actual');
});

it('edits one of several targets for the same representative and year', function () {
    foreach ([1, 2] as $month) {
        Target::create([
            'user_id' => $this->user->id,
            'year' => 2026,
            'month' => $month,
            'monthly_target' => 10_000_000,
        ]);
    }

    $february = Target::query()->where('month', 2)->sole();

    livewire(ManageTargets::class)
        ->mountTableAction('edit', $february)
        ->fillForm(['actual' => 5_000_000])
        ->callMountedTableAction()
        ->assertHasNoFormErrors();

    expect((float) $february->refresh()->actual)->toBe(5_000_000.0);
});

it('leaves no partial rows when a month collides on save', function () {
    $created = false;

    Target::creating(function (Target $target) use (&$created) {
        if ($created || (int) $target->month !== 1) {
            return;
        }

        $created = true;

        Target::insert([
            'user_id' => $target->user_id,
            'year' => $target->year,
            'month' => 2,
            'monthly_target' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    livewire(ManageTargets::class)
        ->callAction('create', [
            'user_id' => $this->user->id,
            'year' => 2026,
            'monthly_targets' => [
                ['month' => 1, 'monthly_target' => 10_000_000],
                ['month' => 2, 'monthly_target' => 20_000_000],
            ],
        ])
        ->assertHasErrors();

    // The rollback takes the colliding insert with it, so the rep is left with
    // no half-written year rather than a partial one.
    expect(Target::query()->count())->toBe(0);
});
