<?php

namespace App\Filament\Resources\Permissions\Tables;

use App\Helpers\PermissionHelper;
use App\Models\Department;
use App\Models\Role;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;

class PermissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table

            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function (string $state): string {
                        $parsed = PermissionHelper::parsePermissionName($state);
                        if ($parsed === null) {
                            return $state;
                        }

                        return PermissionHelper::getActionLabel($parsed['action'])
                            .' '
                            .PermissionHelper::getModelLabel($parsed['model']);
                    }),
                TextColumn::make('model')
                    ->label('Model')
                    ->state(fn ($record): ?string => PermissionHelper::parsePermissionName($record->name)['model'] ?? null)
                    ->formatStateUsing(fn (?string $state): string => $state ? PermissionHelper::getModelLabel($state) : '-')
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Assigned Roles')
                    ->badge()
                    ->color('gray')
                    ->placeholder('None')
                    ->sortable(),
                TextColumn::make('guard_name')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('department')
                    ->label('Department')
                    ->options(fn (): array => Department::orderBy('name')->pluck('name', 'id')->toArray())
                    ->query(function ($query, array $data): void {
                        if (empty($data['values'])) {
                            return;
                        }

                        $departmentIds = is_array($data['values']) ? $data['values'] : [$data['values']];

                        $query->whereHas('roles', function ($q) use ($departmentIds): void {
                            $q->whereIn('department_id', $departmentIds);
                        });
                    })
                    ->multiple(),
                SelectFilter::make('model')
                    ->label('Model')
                    ->options(fn (): array => collect(PermissionHelper::getModels())
                        ->mapWithKeys(fn (string $model): array => [$model => PermissionHelper::getModelLabel($model)])
                        ->sort()
                        ->toArray())
                    ->query(function ($query, array $data): void {
                        if (empty($data['values'])) {
                            return;
                        }

                        $modelSlugs = is_array($data['values']) ? $data['values'] : [$data['values']];

                        $query->where(function ($q) use ($modelSlugs): void {
                            foreach ($modelSlugs as $slug) {
                                $q->orWhere('name', 'like', "%_{$slug}");
                            }
                        });
                    })
                    ->multiple(),
                SelectFilter::make('roles')
                    ->label('Role')
                    ->relationship('roles', 'name', fn ($query) => $query->orderBy('name'))
                    ->multiple()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('assignToRole')
                        ->label('Assign to Role')
                        ->icon('heroicon-m-user-group')
                        ->form([
                            Select::make('role_id')
                                ->label('Role')
                                ->options(function (): array {
                                    $roles = Role::with('department')->orderBy('name')->get();
                                    $grouped = [];
                                    foreach ($roles as $role) {
                                        $group = $role->department?->name ?? 'Global';
                                        $label = $role->department
                                            ? "{$role->name} ({$role->department->name})"
                                            : $role->name;
                                        $grouped[$group][$role->id] = $label;
                                    }
                                    ksort($grouped, SORT_NATURAL);

                                    return $grouped;
                                })
                                ->searchable()
                                ->multiple()
                                ->default(function () use ($table): ?array {
                                    $selectedIds = $table->getLivewire()->selectedTableRecords ?? [];

                                    if (empty($selectedIds)) {
                                        return null;
                                    }

                                    $permissions = Permission::with('roles')
                                        ->whereIn('id', $selectedIds)
                                        ->get();

                                    if ($permissions->isEmpty()) {
                                        return null;
                                    }

                                    $firstRoleIds = $permissions->first()
                                        ->roles->pluck('id')->sort()->values();

                                    $allSame = $permissions->every(
                                        fn ($p) => $p->roles->pluck('id')
                                            ->sort()->values()->toArray() === $firstRoleIds->toArray()
                                    );

                                    return $allSame && $firstRoleIds->isNotEmpty()
                                        ? $firstRoleIds->toArray()
                                        : null;
                                }),
                        ])
                        ->action(function ($records, array $data): void {
                            foreach ($records as $permission) {
                                $permission->roles()->sync($data['role_id']);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
