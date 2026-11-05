<?php

namespace App\Filament\Widgets\Reports;

use App\DTOs\ReportFilterData;
use App\Services\Reports\SalesStatisticsService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

/**
 * Per-person breakdown. Shown whenever a manager is selected, since a rollup
 * total hides who on the team is actually producing.
 */
class SalesStatisticsTeamWidget extends TableWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => $this->getRecords())
            ->columns([
                TextColumn::make('name')
                    ->label('Sales Rep')
                    ->weight('bold'),
                TextColumn::make('orders')
                    ->label('Orders')
                    ->alignRight(),
                TextColumn::make('revenue')
                    ->label('Revenue')
                    ->money('IDR')
                    ->alignRight(),
                TextColumn::make('lead_count')
                    ->label('Leads')
                    ->alignRight(),
                TextColumn::make('lead_value')
                    ->label('Lead Value')
                    ->money('IDR')
                    ->alignRight(),
                TextColumn::make('activities')
                    ->label('Activities')
                    ->alignRight(),
            ])
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No team members')
            ->emptyStateDescription('Pick a Sales-department manager to see their reporting line.');
    }

    public function getTableHeading(): ?string
    {
        $filters = $this->pageFilters ?? [];

        if (blank($filters['manager_id'] ?? null)) {
            return null;
        }

        $data = app(SalesStatisticsService::class)->generate(
            ReportFilterData::fromArray($filters),
            $filters['manager_id'],
        );

        return "Team Breakdown — {$data->managerName}";
    }

    protected function getRecords()
    {
        $filters = $this->pageFilters ?? [];

        if (blank($filters['manager_id'] ?? null)) {
            return collect();
        }

        $data = app(SalesStatisticsService::class)->generate(
            ReportFilterData::fromArray($filters),
            $filters['manager_id'],
        );

        return $data->teamRows;
    }
}
