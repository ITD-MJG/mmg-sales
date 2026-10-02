<?php

use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Activities\Pages\ViewActivity;
use App\Filament\Resources\Opportunities\OpportunityResource;
use App\Filament\Resources\Opportunities\Pages\ViewOpportunity;
use App\Models\Activity;
use App\Models\Opportunity;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('Super Admin');
    actingAs($this->user);
});

function deal(string $code): Opportunity
{
    return Opportunity::factory()->create(['opportunity_code' => $code]);
}

function loggedActivity(string $code): Activity
{
    return Activity::factory()->create(['activity_code' => $code]);
}

/**
 * Header action names in the order they render.
 *
 * @return list<string>
 */
function headerActionNames(string $page, int $recordKey): array
{
    $instance = app($page);
    $instance->record = app($instance::getResource()::getModel())->findOrFail($recordKey);

    return array_map(
        fn ($action): string => $action->getName(),
        (function (): array {
            return $this->getHeaderActions();
        })->call($instance)
    );
}

it('places Prev and Next before Edit on the opportunity view page', function () {
    $opportunity = deal('OPP-202601-0002');

    expect(headerActionNames(ViewOpportunity::class, $opportunity->getKey()))
        ->toBe(['previousRecord', 'nextRecord', 'edit']);
});

it('places Prev and Next before Edit on the activity view page', function () {
    $activity = loggedActivity('ACT-2026-0002');

    expect(headerActionNames(ViewActivity::class, $activity->getKey()))
        ->toBe(['previousRecord', 'nextRecord', 'edit']);
});

it('steps an opportunity forward and back in code order', function () {
    $first = deal('OPP-202601-0001');
    $second = deal('OPP-202601-0002');
    $third = deal('OPP-202601-0003');

    Livewire::test(ViewOpportunity::class, ['record' => $second->getKey()])
        ->assertActionHasUrl('previousRecord', OpportunityResource::getUrl('view', ['record' => $first]))
        ->assertActionHasUrl('nextRecord', OpportunityResource::getUrl('view', ['record' => $third]));
});

it('steps an activity forward and back in code order', function () {
    $first = loggedActivity('ACT-2026-0001');
    $second = loggedActivity('ACT-2026-0002');
    $third = loggedActivity('ACT-2026-0003');

    Livewire::test(ViewActivity::class, ['record' => $second->getKey()])
        ->assertActionHasUrl('previousRecord', ActivityResource::getUrl('view', ['record' => $first]))
        ->assertActionHasUrl('nextRecord', ActivityResource::getUrl('view', ['record' => $third]));
});

it('disables the outbound arrow at each end of the opportunity sequence', function () {
    $first = deal('OPP-202601-0001');
    $last = deal('OPP-202601-0002');

    Livewire::test(ViewOpportunity::class, ['record' => $first->getKey()])
        ->assertActionDisabled('previousRecord')
        ->assertActionEnabled('nextRecord');

    Livewire::test(ViewOpportunity::class, ['record' => $last->getKey()])
        ->assertActionEnabled('previousRecord')
        ->assertActionDisabled('nextRecord');
});

it('disables the outbound arrow at each end of the activity sequence', function () {
    $first = loggedActivity('ACT-2026-0001');
    $last = loggedActivity('ACT-2026-0002');

    Livewire::test(ViewActivity::class, ['record' => $first->getKey()])
        ->assertActionDisabled('previousRecord')
        ->assertActionEnabled('nextRecord');

    Livewire::test(ViewActivity::class, ['record' => $last->getKey()])
        ->assertActionEnabled('previousRecord')
        ->assertActionDisabled('nextRecord');
});

it('handles an opportunity with no siblings', function () {
    $only = deal('OPP-202601-0001');

    Livewire::test(ViewOpportunity::class, ['record' => $only->getKey()])
        ->assertActionDisabled('previousRecord')
        ->assertActionDisabled('nextRecord');
});

it('names the neighbouring record in the tooltip', function () {
    deal('OPP-202601-0001');
    $second = deal('OPP-202601-0002');

    Livewire::test(ViewOpportunity::class, ['record' => $second->getKey()])
        ->assertActionHasLabel('previousRecord', 'Prev')
        ->assertActionHasLabel('nextRecord', 'Next');
});

it('denies staff the opportunity view page now that it is locked to Super Admin', function () {
    $staff = User::factory()->create();
    $staff->assignRole('Sales Staff');
    actingAs($staff);

    $opportunity = Opportunity::factory()->create(['opportunity_code' => 'OPP-202601-0001']);

    $this->get(OpportunityResource::getUrl('view', ['record' => $opportunity]))->assertForbidden();
});
