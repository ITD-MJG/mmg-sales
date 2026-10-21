<?php

use App\Models\Lead;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('stores expected revenue and selected products on a lead', function () {
    $lead = Lead::factory()->create(['estimated_revenue' => 1250000.50]);
    $products = Product::factory()->count(2)->create();

    $lead->products()->sync($products->modelKeys());

    expect($lead->fresh()->estimated_revenue)->toBe('1250000.50')
        ->and($lead->products()->pluck('products.id')->all())->toEqualCanonicalizing($products->modelKeys())
        ->and(Schema::hasTable('lead_product'))->toBeTrue();
});

it('stores selected collaborators while preserving creator and primary assignee', function () {
    $creator = User::factory()->create();
    $assignee = User::factory()->create();
    $collaborator = User::factory()->create();
    $lead = Lead::factory()->create([
        'created_by' => $creator->id,
        'assigned_to' => $assignee->id,
    ]);

    $lead->collaborators()->attach($collaborator->id, ['added_by' => $creator->id]);

    expect($lead->fresh()->created_by)->toBe($creator->id)
        ->and($lead->fresh()->assigned_to)->toBe($assignee->id)
        ->and($lead->collaborators()->pluck('users.id')->all())->toBe([$collaborator->id])
        ->and(Schema::hasTable('lead_collaborators'))->toBeTrue();
});
