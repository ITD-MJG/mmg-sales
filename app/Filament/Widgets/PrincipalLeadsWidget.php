<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Principals\Tables\PrincipalLeadsTable;
use App\Models\Principal;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PrincipalLeadsWidget extends TableWidget
{
    public ?Model $record = null;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected function getTableQuery(): Builder
    {
        return PrincipalLeadsTable::query($this->getPrincipalRecord());
    }

    private function getPrincipalRecord(): Principal
    {
        abort_unless($this->record instanceof Principal, 404);

        return $this->record;
    }

    public function table(Table $table): Table
    {
        return PrincipalLeadsTable::configure($table)
            ->heading('Leads');
    }
}
