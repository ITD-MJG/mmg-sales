<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class UserHierarchy extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'User Hierarchy';

    protected static ?string $title = 'User Hierarchy';

    protected static string|\UnitEnum|null $navigationGroup = 'System Settings';

    protected static ?int $navigationSort = 0;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->with(['position', 'territory', 'manager']))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('position.name')
                    ->label('Position')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('territory.name')
                    ->label('Territory')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('manager.name')
                    ->label('Manager')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('editHierarchy')
                    ->label('Edit Hierarchy')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('Edit User Hierarchy')
                    ->modalWidth('md')
                    ->form([
                        Select::make('position_id')
                            ->label('Position')
                            ->relationship('position', 'name')
                            ->preload()
                            ->searchable(),
                        Select::make('territory_id')
                            ->label('Territory')
                            ->relationship('territory', 'name')
                            ->preload()
                            ->searchable(),
                        Select::make('manager_id')
                            ->label('Manager')
                            ->relationship('manager', 'name')
                            ->preload()
                            ->searchable(),
                    ])

                    ->fillForm(fn (User $record): array => [
                        'position_id' => $record->position_id,
                        'territory_id' => $record->territory_id,
                        'manager_id' => $record->manager_id,
                    ])
                    ->action(function (array $data, User $record): void {
                        $record->update($data);

                        Notification::make()
                            ->success()
                            ->title("{$record->name}'s hierarchy updated")
                            ->send();
                    }),
            ])
            ->modifyQueryUsing(fn ($query) => $query->orderBy('name'));
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }
}
