<?php

namespace Tests\Feature;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\LeadsRelationManager;
use App\Filament\Widgets\CustomerActivityStatsWidget;
use App\Filament\Widgets\CustomerCommentHistoryWidget;
use App\Filament\Widgets\CustomerLeadsWidget;
use App\Filament\Widgets\CustomerRecentActivitiesWidget;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('Super Admin');
    actingAs($this->user);
    $this->customer = Customer::factory()->create();
});

it('renders the customer widgets in the two column layout', function () {
    $response = $this->get(CustomerResource::getUrl('view', ['record' => $this->customer]));

    $response->assertSuccessful()
        ->assertSeeLivewire(CustomerActivityStatsWidget::class)
        ->assertSeeLivewire(CustomerRecentActivitiesWidget::class)
        ->assertSeeLivewire(CustomerCommentHistoryWidget::class)
        ->assertSeeLivewire(CustomerLeadsWidget::class);
});

it('puts recent activities and comment history side by side with leads below comment history', function () {
    expect((new CustomerRecentActivitiesWidget)->getColumnSpan())->toBe(1)
        ->and((new CustomerCommentHistoryWidget)->getColumnSpan())->toBe(1)
        ->and((new CustomerLeadsWidget)->getColumnSpan())->toBe(1)
        ->and((new CustomerLeadsWidget)->getColumnStart())->toBe(2);

    $widgets = (fn (): array => $this->getHeaderWidgets())->call(new ViewCustomer);

    expect($widgets)->toBe([
        CustomerActivityStatsWidget::class,
        CustomerRecentActivitiesWidget::class,
        CustomerCommentHistoryWidget::class,
        CustomerLeadsWidget::class,
    ]);
});

it('keeps the leads relation manager off the view page but on the edit page', function () {
    expect(CustomerResource::getRelations())->toContain(
        LeadsRelationManager::class
    );

    expect(LeadsRelationManager::canViewForRecord($this->customer, ViewCustomer::class))
        ->toBeFalse()
        ->and(LeadsRelationManager::canViewForRecord($this->customer, EditCustomer::class))
        ->toBeTrue();
});
