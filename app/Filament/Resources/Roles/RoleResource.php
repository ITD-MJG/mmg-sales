<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Filament\Resources\Roles\Tables\RolesTable;
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
     * Role names are the position name, and nothing else.
     *
     * Four formats have existed here: "{Position} - {Department}",
     * "{Department} {Position}", the raw position name, and assorted legacy
     * literals. Production ran on "{Position} - {Department}" while the code
     * checked bare position names, so every role check silently failed.
     *
     * Create and edit must both call this. They previously each carried their
     * own copy and drifted apart, which is how the naming broke in the first
     * place.
     *
     * The department is deliberately NOT part of the name. It is already a
     * column on the role, and prefixing it produced names like
     * "Sales Sales Supervisor Clinical Diagnostic".
     */
    public static function generateRoleName(?int $positionId, ?int $departmentId = null): string
    {
        return Position::find($positionId)?->name ?? 'Unnamed';
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
