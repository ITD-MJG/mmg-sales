<?php

namespace App\Filament\Resources\Targets;

use App\Filament\Resources\Targets\Pages\ManageTargets;
use App\Filament\Traits\HasVisibilityScope;
use App\Models\Target;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ManageRecords;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class TargetResource extends Resource
{
    use HasVisibilityScope;

    protected static ?string $model = Target::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Sales Representative')
                    ->relationship('user', 'name')
                    ->required()
                    // One row per rep per month, so the target and the actual
                    // the rep reports for that period stay on the same record.
                    ->rule(fn (Get $get, Component $livewire): Unique => Rule::unique('targets', 'user_id')
                        ->where(fn ($query) => $query
                            ->where('year', $get('year'))
                            ->where('month', $get('month')))
                        ->ignore(self::currentRecordKey($livewire)))
                    ->validationMessages([
                        'unique' => 'This representative already has a target for that month. Edit the existing row instead.',
                    ]),
                TextInput::make('year')
                    ->label('Year')
                    ->required()
                    ->numeric()
                    ->minValue(2020)
                    ->maxValue(2030),
                Select::make('month')
                    ->label('Month')
                    ->options(self::monthOptions())
                    ->required(),
                TextInput::make('monthly_target')
                    ->label('Monthly Target')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),
                // The rep reports achievement against their own month, so the
                // actual is entered on an existing target rather than alongside
                // the target it is measured against.
                TextInput::make('actual')
                    ->label('Actual')
                    ->numeric()
                    ->prefix('Rp')
                    ->nullable()
                    ->helperText('Revenue the rep recorded for this month.')
                    ->hiddenOn('create'),
            ]);
    }

    /**
     * @return array<int, string>
     */
    public static function monthOptions(): array
    {
        return collect(range(1, 12))
            ->mapWithKeys(fn (int $month): array => [$month => Carbon::create()->month($month)->format('F')])
            ->all();
    }

    /**
     * The row being edited, so the uniqueness rule does not reject an unchanged
     * target on save.
     */
    protected static function currentRecordKey(?Component $livewire): ?int
    {
        if (! $livewire instanceof ManageRecords) {
            return null;
        }

        $mounted = $livewire->mountedActions[0] ?? null;

        return $mounted['context']['recordKey'] ?? null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Sales Representative')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('year')
                    ->label('Year')
                    ->sortable(),
                TextColumn::make('month')
                    ->label('Month')
                    ->formatStateUsing(fn ($state) => $state ? Carbon::create()->month($state)->format('F') : 'N/A')
                    ->sortable(),
                TextColumn::make('monthly_target')
                    ->label('Target')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('actual')
                    ->label('Actual')
                    ->money('IDR')
                    ->sortable()
                    ->placeholder('Not reported'),
                TextColumn::make('variance')
                    ->label('Variance')
                    ->state(fn (?Target $record): float => (float) ($record?->actual ?? 0) - (float) ($record?->monthly_target ?? 0))
                    ->money('IDR')
                    ->color(fn (?Target $record): string => (float) ($record?->actual ?? 0) >= (float) ($record?->monthly_target ?? 0) ? 'success' : 'danger')
                    ->placeholder('-')
                    ->visible(fn (?Target $record): bool => $record?->actual !== null),
            ])
            ->filters([
                //
            ])
            ->modifyQueryUsing(fn (Builder $query) => self::applyVisibilityScope($query, 'user_id'))
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTargets::route('/'),
        ];
    }
}
