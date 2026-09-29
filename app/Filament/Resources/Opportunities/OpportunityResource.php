<?php

namespace App\Filament\Resources\Opportunities;

use App\Filament\Resources\Opportunities\Pages\CreateOpportunity;
use App\Filament\Resources\Opportunities\Pages\EditOpportunity;
use App\Filament\Resources\Opportunities\Pages\ListOpportunities;
use App\Filament\Resources\Opportunities\Pages\ViewOpportunity;
use App\Filament\Resources\Opportunities\Schemas\OpportunityForm;
use App\Filament\Resources\Opportunities\Schemas\OpportunityInfolist;
use App\Filament\Resources\Opportunities\Tables\OpportunitiesTable;
use App\Models\Opportunity;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OpportunityResource extends Resource
{
    protected static ?string $model = Opportunity::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return OpportunityForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OpportunityInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OpportunitiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CollaboratorsRelationManager::class,
            RelationManagers\ActivitiesRelationManager::class,
            RelationManagers\ActivityCommentsRelationManager::class,
            RelationManagers\AuditLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOpportunities::route('/'),
            'create' => CreateOpportunity::route('/create'),
            'view' => ViewOpportunity::route('/{record}'),
            'edit' => EditOpportunity::route('/{record}/edit'),
        ];
    }
}
