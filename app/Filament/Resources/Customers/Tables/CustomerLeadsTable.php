<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Filament\Actions\ConvertLeadToOpportunityAction;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Leads\Tables\LeadsTable;
use App\Models\Lead;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CustomerLeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('lead_code')
                    ->label('#')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(40)
                    ->tooltip(fn (Lead $record): string => $record->title),

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
                    ->sortable()
                    ->summarize(Sum::make()->money('IDR')->label('Total')),

                TextColumn::make('expected_closing_date')
                    ->label('Closing')
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
            ->headerActions([
                Action::make('create')
                    ->label('New Lead')
                    ->icon('heroicon-m-plus')
                    ->url(fn (): string => LeadResource::getUrl('create')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (Lead $record): bool => LeadsTable::canModifyRecord($record, 'created_by'))
                    ->url(fn (Lead $record): string => LeadResource::getUrl('edit', ['record' => $record])),
                ConvertLeadToOpportunityAction::make(),
                DeleteAction::make()
                    ->visible(fn (Lead $record): bool => LeadsTable::canModifyRecord($record, 'created_by')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->hasAnyRole([
                            'Super Admin',
                            'Sales Staff',
                            'Marketing Staff',
                            'Logistics Staff',
                            'Finance & Accounting Staff',
                            'Sales Supervisor',
                            'Import & Purchasing Supervisor',
                            'Regional Sales Manager',
                            'Area Sales Manager',
                        ])),
                ]),
            ]);
    }
}
