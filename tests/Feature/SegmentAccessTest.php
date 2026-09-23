<?php

namespace Tests\Feature;

use App\Filament\Resources\Segments\SegmentResource;
use App\Filament\Resources\SubSegments\SubSegmentResource;
use App\Models\Segment;
use App\Models\SubSegment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('Super Admin');

    $this->salesRep = User::factory()->create();
    $this->salesRep->assignRole('Sales Staff');
});

it('allows Super Admin to view segments and subsegments', function () {
    actingAs($this->superAdmin);

    expect($this->superAdmin->can('viewAny', Segment::class))->toBeTrue();
    expect($this->superAdmin->can('viewAny', SubSegment::class))->toBeTrue();

    expect(SegmentResource::shouldRegisterNavigation())->toBeTrue();
    expect(SubSegmentResource::shouldRegisterNavigation())->toBeTrue();
});

it('allows Sales Staff to view segments and subsegments as reference data', function () {
    actingAs($this->salesRep);

    // Reference data is viewable by every Sales & Marketing role (view_any_segment
    // ships in the shared reference permission set), which matches production.
    expect($this->salesRep->can('viewAny', Segment::class))->toBeTrue();
    expect($this->salesRep->can('viewAny', SubSegment::class))->toBeTrue();

    expect(SegmentResource::shouldRegisterNavigation())->toBeTrue();
    expect(SubSegmentResource::shouldRegisterNavigation())->toBeTrue();
});
