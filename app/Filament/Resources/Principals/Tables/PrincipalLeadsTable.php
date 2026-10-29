<?php

namespace App\Filament\Resources\Principals\Tables;

use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Leads\Tables\LeadsTable;
use App\Models\Lead;
use App\Models\Principal;
use App\Services\PrincipalMetricsService;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PrincipalLeadsTable
{
    public static function query(Principal $principal): Builder
    {
        return PrincipalMetricsService::leadsQuery($principal, auth()->user())
            ->with(['latestActivity'])
            ->orderByDesc('leads.created_at');
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lead_code')
                    ->label('Lead Code')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(28)
                    ->tooltip(fn (Lead $record): string => $record->title),

                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->limit(20)
                    ->tooltip(fn (?string $state): ?string => $state),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'converted' => 'success',
                        'disqualified' => 'danger',
                        'contacted' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('estimated_revenue')
                    ->label('Exp. Revenue')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('expected_closing_date')
                    ->label('Exp. Closing')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'new' => 'New',
                        'contacted' => 'Contacted',
                        'converted' => 'Converted',
                        'disqualified' => 'Disqualified',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Lead $record): string => LeadResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->visible(fn (Lead $record): bool => LeadsTable::canModifyRecord($record, 'created_by'))
                    ->url(fn (Lead $record): string => LeadResource::getUrl('edit', ['record' => $record])),

            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5);
    }
}
