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
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
                    // The targets unique key is (user_id, year, month), but SQL
                    // treats a NULL month as distinct, so a year's annual row can
                    // be inserted twice. Guard it here: one per rep per year.
                    ->rule(fn (Get $get): Unique => Rule::unique('targets', 'user_id')
                        ->where(fn ($query) => $query
                            ->where('year', $get('year'))
                            ->whereNull('month'))
                        ->ignore(self::currentRecordKey()),
                        fn (Get $get): bool => $get('scope') === 'annual')
                    ->rule(fn (Get $get): Unique => Rule::unique('targets', 'user_id')
                        ->where(fn ($query) => $query
                            ->where('year', $get('year'))
                            ->where('month', $get('month')))
                        ->ignore(self::currentRecordKey()),
                        fn (Get $get): bool => $get('scope') === 'month')
                    ->validationMessages([
                        'unique' => 'This representative already has a target for that period. Edit the existing row instead.',
                    ]),
                TextInput::make('year')
                    ->label('Year')
                    ->required()
                    ->numeric()
                    ->minValue(2020)
                    ->maxValue(2030),
                // Not a targets column: an annual target is a single row with a
                // null month, so this picks which amount is persisted and how the
                // uniqueness rules above are scoped.
                Select::make('scope')
                    ->label('Target Type')
                    ->options([
                        'annual' => 'Annual (spread over 12 months)',
                        'month' => 'Specific month',
                    ])
                    ->default('annual')
                    ->selectablePlaceholder(false)
                    ->live()
                    // Dehydrated so the payload can be normalized before saving.
                    ->dehydrated()
                    ->afterStateHydrated(function (Set $set, ?Target $record): void {
                        if ($record) {
                            $set('scope', $record->month === null ? 'annual' : 'month');
                        }
                    })
                    ->required(),
                TextInput::make('annual_target')
                    ->label('Annual Target')
                    ->numeric()
                    ->prefix('Rp')
                    ->nullable()
                    ->required(fn (Get $get): bool => $get('scope') === 'annual')
                    ->visible(fn (Get $get): bool => $get('scope') === 'annual')
                    ->helperText('Stored on one row for the whole year. Months without their own target share it evenly.'),
                Select::make('month')
                    ->label('Month')
                    ->options([
                        1 => 'January',
                        2 => 'February',
                        3 => 'March',
                        4 => 'April',
                        5 => 'May',
                        6 => 'June',
                        7 => 'July',
                        8 => 'August',
                        9 => 'September',
                        10 => 'October',
                        11 => 'November',
                        12 => 'December',
                    ])
                    ->nullable()
                    ->required(fn (Get $get): bool => $get('scope') === 'month')
                    ->visible(fn (Get $get): bool => $get('scope') === 'month'),
                TextInput::make('monthly_target')
                    ->label('Monthly Target')
                    ->numeric()
                    ->prefix('Rp')
                    ->nullable()
                    ->required(fn (Get $get): bool => $get('scope') === 'month')
                    ->visible(fn (Get $get): bool => $get('scope') === 'month'),
            ]);
    }

    /**
     * The row being edited, so the uniqueness rules do not reject an unchanged
     * annual or monthly target on save.
     */
    protected static function currentRecordKey(): ?int
    {
        $page = app(ManageRecords::class);

        return method_exists($page, 'getRecord') && $page->getRecord()
            ? $page->getRecord()->getKey()
            : null;
    }

    /**
     * A target covers one amount scope, so the opposite amount is blanked rather
     * than left behind: summing `annual_target` across a year's rows would
     * otherwise multiply a rep's annual quota by the number of monthly rows.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeTargetPayload(array $data): array
    {
        if (($data['scope'] ?? null) === 'annual') {
            $data['month'] = null;
            $data['monthly_target'] = null;
        } else {
            $data['annual_target'] = null;
        }

        return $data;
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
                TextColumn::make('annual_target')
                    ->label('Annual Target')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('monthly_target')
                    ->label('Monthly Target')
                    ->money('IDR')
                    ->sortable(),
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
