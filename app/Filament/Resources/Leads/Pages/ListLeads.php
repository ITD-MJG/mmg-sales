<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use App\Livewire\LeadBoard;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    /**
     * Which of the two views is showing: 'table' or 'board'.
     *
     * Deliberately separate from HasTabs::$activeTab, which ListRecords uses for
     * query-filter tabs. Reusing it would collide with that mechanism.
     */
    public string $viewTab = 'table';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make()
                    ->livewireProperty('viewTab')
                    ->contained(false)
                    ->tabs([
                        'table' => Tab::make('Table')
                            ->icon(Heroicon::OutlinedTableCells)
                            ->schema([
                                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                                EmbeddedTable::make(),
                                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
                            ]),
                        'board' => Tab::make('Board')
                            ->icon(Heroicon::OutlinedViewColumns)
                            ->schema([
                                Livewire::make(LeadBoard::class),
                            ]),
                    ]),
            ]);
    }
}
