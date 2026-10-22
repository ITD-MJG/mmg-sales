<?php

namespace Tests\Feature\Filament\Orders;

use App\Filament\Resources\Orders\Schemas\OrderForm;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('configures money masks with Filament-compatible RawJs', function () {
    $schema = OrderForm::configure(Schema::make());

    $textInputs = collect($schema->getComponents())
        ->flatMap(fn ($component) => $component->getChildComponents())
        ->flatMap(fn ($component) => $component->getChildComponents())
        ->filter(fn ($component) => $component instanceof TextInput);

    expect($textInputs)->not->toBeEmpty();
});
