<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Customers\Tables\CustomerLeadsTable;
use App\Models\Lead;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerLeadsWidget extends TableWidget
{
    public ?Model $record = null;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected int|string|array $columnStart = 2;

    public function table(Table $table): Table
    {
        return CustomerLeadsTable::configure(
            $table
                ->query(fn (): Builder => Lead::query()
                    ->where('customer_id', $this->record?->getKey())
                    ->with(['creator:id,name', 'collaborators:id,name', 'latestActivity'])
                    ->orderByDesc('created_at'))
                ->heading('Leads')
        )->paginated([5, 10])
            ->defaultPaginationPageOption(5);
    }
}
