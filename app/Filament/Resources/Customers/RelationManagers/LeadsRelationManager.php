<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\Tables\CustomerLeadsTable;
use App\Filament\Resources\Leads\Schemas\LeadInfolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LeadsRelationManager extends RelationManager
{
    protected static string $relationship = 'leads';

    protected static ?string $title = 'Leads';

    /**
     * The view page renders the same leads table as a widget next to the
     * comment history, so the relation manager only belongs on the edit page.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $pageClass !== ViewCustomer::class
            && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function infolist(Schema $schema): Schema
    {
        return LeadInfolist::configure($schema);
    }

    public function table(Table $table): Table
    {
        return CustomerLeadsTable::configure(
            $table->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['creator:id,name', 'collaborators:id,name', 'latestActivity'])
                ->orderByDesc('created_at'))
        );
    }
}
