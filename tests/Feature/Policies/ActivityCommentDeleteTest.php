<?php

namespace Tests\Feature\Policies;

use App\Filament\Resources\Leads\Pages\ViewLead;
use App\Filament\Resources\Leads\RelationManagers\ActivityCommentsRelationManager;
use App\Models\Activity;
use App\Models\ActivityComment;
use App\Models\Department;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

/**
 * Deleting a comment is a normal write: the author needs the
 * `delete_activity_comment` permission on top of authorship. The delete button
 * follows the same rule, so an author whose role does not carry the permission
 * keeps the edit button and loses the delete one.
 */
beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);

    $this->lead = Lead::factory()->create();
    $this->activity = Activity::factory()->forLead($this->lead)->create();
});

/**
 * The base set is everything an author needs to work the relation manager; the
 * delete permission is the one under test and is only added where asked.
 *
 * @param  list<string>  $extra
 */
function commentAuthor(array $extra = []): User
{
    Role::findOrCreate('Comment Author')->syncPermissions([
        'view_activity_comment',
        'view_any_activity_comment',
        'create_activity_comment',
        'update_activity_comment',
        ...$extra,
    ]);

    $user = User::factory()->create();
    $user->assignRole('Comment Author');

    return $user;
}

it('denies deleting when the role carries no delete permission', function () {
    $author = commentAuthor();

    expect($author->hasPermissionTo('delete_activity_comment'))->toBeFalse();

    $comment = ActivityComment::factory()->create([
        'activity_id' => $this->activity->id,
        'user_id' => $author->id,
    ]);

    expect(Gate::forUser($author)->allows('delete', $comment))->toBeFalse();
});

it('hides the delete button from that author but keeps the edit button', function () {
    $author = commentAuthor();
    $comment = ActivityComment::factory()->create([
        'activity_id' => $this->activity->id,
        'user_id' => $author->id,
    ]);

    actingAs($author);

    livewire(ActivityCommentsRelationManager::class, [
        'ownerRecord' => $this->lead,
        'pageClass' => ViewLead::class,
    ])
        ->assertTableActionVisible('edit', $comment)
        ->assertTableActionHidden('delete', $comment);
});

it('lets an author holding the delete permission remove their own comment', function () {
    $author = commentAuthor(['delete_activity_comment']);
    $comment = ActivityComment::factory()->create([
        'activity_id' => $this->activity->id,
        'user_id' => $author->id,
    ]);

    actingAs($author);

    $component = livewire(ActivityCommentsRelationManager::class, [
        'ownerRecord' => $this->lead,
        'pageClass' => ViewLead::class,
    ]);

    $component->assertTableActionVisible('delete', $comment);
    $component->callTableAction('delete', $comment)
        ->assertHasNoTableActionErrors();

    expect(ActivityComment::query()->whereKey($comment->id)->exists())->toBeFalse();
});

it('still refuses to delete a comment written by someone else', function () {
    $author = commentAuthor(['delete_activity_comment']);

    $someoneElse = User::factory()->create();
    $someoneElse->assignRole('Sales Staff');

    $comment = ActivityComment::factory()->create([
        'activity_id' => $this->activity->id,
        'user_id' => $someoneElse->id,
    ]);

    expect(Gate::forUser($author)->allows('delete', $comment))->toBeFalse();
});

it('still denies a global viewer who authored the comment', function () {
    // A Director in the Management department: global viewer, never destructive.
    $management = Department::factory()->create(['name' => Department::MANAGEMENT]);
    $director = User::factory()->create(['department_id' => $management->id]);
    $director->assignRole('Management Director');

    $comment = ActivityComment::factory()->create([
        'activity_id' => $this->activity->id,
        'user_id' => $director->id,
    ]);

    expect(Gate::forUser($director)->allows('delete', $comment))->toBeFalse();
});
