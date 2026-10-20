<?php

use App\Filament\Resources\Reports\Pages\TargetReportPage;
use App\Filament\Widgets\Reports\TargetReportStatsWidget;
use App\Filament\Widgets\Reports\TargetVsLeadWidget;
use App\Filament\Widgets\Reports\TargetVsOrderWidget;
use App\Models\Target;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function targetReportViewer(): User
{
    $user = User::factory()->create();
    $user->assignRole('Sales Manager');
    actingAs($user);

    return $user;
}

it('renders the target report page for a user with sales report access', function () {
    targetReportViewer();

    Livewire::test(TargetReportPage::class)->assertSuccessful();
});

it('renders the target report page with no targets on record', function () {
    targetReportViewer();

    expect(Target::count())->toBe(0);

    // Widgets load lazily, so the page asserts its filter form and the
    // widget registration is asserted directly.
    Livewire::test(TargetReportPage::class)
        ->assertSuccessful()
        ->assertSee('Start Date')
        ->assertSee('End Date');

    $widgets = (fn (): array => $this->getFooterWidgets())->call(new TargetReportPage);
    expect($widgets)->toContain(TargetVsLeadWidget::class, TargetVsOrderWidget::class);
});

it('builds target vs lead datasets from targets and opportunities', function () {
    $user = targetReportViewer();

    Target::create([
        'user_id' => $user->id,
        'year' => now()->year,
        'month' => 1,
        'monthly_target' => 1000000,
    ]);

    Livewire::test(TargetVsLeadWidget::class)
        ->assertSuccessful()
        ->assertSee('Lead Value');

    Livewire::test(TargetVsOrderWidget::class)
        ->assertSuccessful()
        ->assertSee('Order Value');

    Livewire::test(TargetReportStatsWidget::class)
        ->assertSuccessful()
        ->assertSee('Total Target');
});

it('lays the two comparison charts side by side under a full-width stats row', function () {
    targetReportViewer();

    $page = new TargetReportPage;

    expect($page->getFooterWidgetsColumns())->toBe(2)
        ->and((fn () => $this->columnSpan)->call(new TargetVsLeadWidget))->toBe(1)
        ->and((fn () => $this->columnSpan)->call(new TargetVsOrderWidget))->toBe(1)
        ->and((fn () => $this->columnSpan)->call(new TargetReportStatsWidget))->toBe('full');
});
