<?php

namespace App\Filament\Resources\Targets;

use App\Filament\Resources\Targets\Pages\ManageTargets;
use App\Models\Target;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TargetResource extends Resource
{
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
                    ->required(),
                TextInput::make('year')
                    ->label('Year')
                    ->required()
                    ->numeric()
                    ->minValue(2020)
                    ->maxValue(2030),
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
                    ->nullable(),
                TextInput::make('annual_target')
                    ->label('Annual Target')
                    ->numeric()
                    ->prefix('Rp')
                    ->nullable(),
                TextInput::make('monthly_target')
                    ->label('Monthly Target')
                    ->numeric()
                    ->prefix('Rp')
                    ->nullable(),
            ]);
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
            ->modifyQueryUsing(function ($query): void {
                $user = auth()->user();

                if (! $user->hasGlobalVisibility()) {
                    $query->where('user_id', $user->id);
                }
            })
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
