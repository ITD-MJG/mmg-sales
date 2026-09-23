<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Filament\Resources\Roles\Tables\RolesTable;
use App\Models\Department;
use App\Models\Position;
use App\Models\Role;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'System Settings';

    protected static ?string $navigationParentItem = 'Users';

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    /**
     * Role names follow "{Department} {Position}".
     *
     * Create and edit must both use this single implementation. They previously
     * each carried their own copy and drifted apart, so saving a role from the
     * edit page silently rewrote its name to the older
     * "{Position} - {Department}" format and broke every role-name check.
     */
    public static function generateRoleName(?int $positionId, ?int $departmentId): string
    {
        $position = $positionId ? Position::find($positionId) : null;
        $department = $departmentId ? Department::find($departmentId) : null;

        $name = $position?->name ?? 'Unnamed';

        if ($department) {
            $name = $department->name.' '.$name;
        }

        return $name;
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
