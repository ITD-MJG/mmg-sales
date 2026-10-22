<?php

use App\Models\Activity;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('attaches the fixed activity attendees to all existing activities without changing their sales reps', function () {
    $barat = Territory::factory()->create(['name' => 'Barat']);
    $timur = Territory::factory()->create(['name' => 'Timur']);
    $other = Territory::factory()->create(['name' => 'Other']);

    $aart = User::factory()->create(['name' => 'Aart van Wingerden']);
    $donny = User::factory()->create(['name' => 'Donny Seprana']);
    $hapsarendra = User::factory()->create(['name' => 'Hapsarendra', 'territory_id' => $barat->id]);
    $rudi = User::factory()->create(['name' => 'Rudi Candra Aditama', 'territory_id' => $timur->id]);

    $baratRep = User::factory()->create(['territory_id' => $barat->id]);
    $timurRep = User::factory()->create(['territory_id' => $timur->id]);
    $otherRep = User::factory()->create(['territory_id' => $other->id]);

    $baratActivity = Activity::factory()->create(['user_id' => $baratRep->id]);
    $timurActivity = Activity::factory()->create(['user_id' => $timurRep->id]);
    $otherActivity = Activity::factory()->create(['user_id' => $otherRep->id]);

    foreach (Activity::all() as $activity) {
        $attendeeIds = [$aart->id, $donny->id];

        if ($activity->user->territory_id === $barat->id) {
            $attendeeIds[] = $hapsarendra->id;
        }

        if ($activity->user->territory_id === $timur->id) {
            $attendeeIds[] = $rudi->id;
        }

        $activity->attendees()->sync($attendeeIds);
    }

    expect($baratActivity->fresh()->user_id)->toBe($baratRep->id)
        ->and($baratActivity->attendees()->pluck('users.id')->all())->toEqualCanonicalizing([$aart->id, $donny->id, $hapsarendra->id])
        ->and($timurActivity->fresh()->user_id)->toBe($timurRep->id)
        ->and($timurActivity->attendees()->pluck('users.id')->all())->toEqualCanonicalizing([$aart->id, $donny->id, $rudi->id])
        ->and($otherActivity->fresh()->user_id)->toBe($otherRep->id)
        ->and($otherActivity->attendees()->pluck('users.id')->all())->toEqualCanonicalizing([$aart->id, $donny->id])
        ->and(DB::table('activity_user')->count())->toBe(8);
});
