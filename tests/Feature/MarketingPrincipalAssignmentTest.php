<?php

use App\Models\Principal;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('attaches principals to a user and exposes cached ids', function () {
    $user = User::factory()->create();
    $first = Principal::factory()->create();
    $second = Principal::factory()->create();

    $user->principals()->attach([$first->id, $second->id]);

    expect($user->assignedPrincipalIds())->toEqualCanonicalizing([$first->id, $second->id])
        ->and($user->managesPrincipal($first->id))->toBeTrue()
        ->and($user->managesPrincipal(Principal::factory()->create()))->toBeFalse()
        ->and($first->marketingUsers()->pluck('users.id')->all())->toEqual([$user->id]);
});

it('rejects a duplicate assignment', function () {
    $user = User::factory()->create();
    $principal = Principal::factory()->create();

    $user->principals()->attach($principal->id);

    expect(fn () => $user->principals()->attach($principal->id))
        ->toThrow(QueryException::class);
});
