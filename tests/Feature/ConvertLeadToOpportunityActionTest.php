<?php

use App\Filament\Actions\ConvertLeadToOpportunityAction;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Filament\Resources\Leads\Pages\ViewLead;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function superAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    return $user;
}

it('offers the convert action on the lead list to a super admin', function () {
    actingAs(superAdmin());

    $lead = Lead::factory()->create(['customer_id' => Customer::factory()]);

    Livewire::test(ListLeads::class)
        ->assertTableActionVisible('convertToOpportunity', $lead);
});

it('hides the convert action from sales staff', function () {
    $staff = User::factory()->create();
    $staff->assignRole('Sales Staff');
    actingAs($staff);

    $lead = Lead::factory()->create([
        'customer_id' => Customer::factory(),
        'created_by' => $staff->id,
    ]);

    Livewire::test(ListLeads::class)
        ->assertTableActionHidden('convertToOpportunity', $lead);
});

it('hides the convert action from a management director', function () {
    $director = User::factory()->create();
    $director->assignRole('Management Director');
    actingAs($director);

    $lead = Lead::factory()->create(['customer_id' => Customer::factory()]);

    expect(ConvertLeadToOpportunityAction::canConvert($lead))->toBeFalse();
});

it('hides the convert action from a user with no roles', function () {
    actingAs(User::factory()->create());

    $lead = Lead::factory()->create(['customer_id' => Customer::factory()]);

    expect(ConvertLeadToOpportunityAction::canConvert($lead))->toBeFalse();
});

it('hides the convert action on a lead with no customer', function () {
    actingAs(superAdmin());

    $lead = Lead::factory()->create(['customer_id' => null]);

    Livewire::test(ListLeads::class)
        ->assertTableActionHidden('convertToOpportunity', $lead);
});

it('hides the convert action on an already-converted lead', function () {
    actingAs(superAdmin());

    $lead = Lead::factory()->converted()->create(['customer_id' => Customer::factory()]);

    Livewire::test(ListLeads::class)
        ->assertTableActionHidden('convertToOpportunity', $lead);
});

it('converts the lead and notifies without redirecting', function () {
    actingAs(superAdmin());

    $lead = Lead::factory()->create(['customer_id' => Customer::factory()]);

    Livewire::test(ListLeads::class)
        ->callTableAction('convertToOpportunity', $lead)
        ->assertHasNoTableActionErrors();

    $opportunity = Opportunity::where('converted_from_lead_id', $lead->id)->first();

    expect($opportunity)->not->toBeNull()
        ->and($opportunity->stage)->toBe('qualified')
        ->and($lead->fresh()->status)->toBe('converted');
});

it('offers the convert action as a header action on the lead view page', function () {
    actingAs(superAdmin());

    $lead = Lead::factory()->create(['customer_id' => Customer::factory()]);

    Livewire::test(ViewLead::class, ['record' => $lead->getKey()])
        ->assertActionVisible('convertToOpportunity');
});
