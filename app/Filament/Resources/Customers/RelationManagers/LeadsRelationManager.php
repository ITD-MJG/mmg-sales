<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Actions\ConvertLeadToOpportunityAction;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Leads\Schemas\LeadInfolist;
use App\Filament\Resources\Leads\Tables\LeadsTable;
use App\Models\Lead;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeadsRelationManager extends RelationManager
{
    protected static string $relationship = 'leads';

    protected static ?string $title = 'Leads';

    public function infolist(Schema $schema): Schema
    {
        return LeadInfolist::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['creator:id,name', 'collaborators:id,name', 'latestActivity'])
                ->orderByDesc('created_at'))
            ->columns([
                TextColumn::make('lead_code')
                    ->label('Lead Code')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

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

                TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'low' => 'gray',
                        'medium' => 'info',
                        'high' => 'warning',
                        'urgent' => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('estimated_revenue')
                    ->label('Exp. Revenue')
                    ->money('IDR')
                    ->sortable()
                    ->summarize(Sum::make()->money('IDR')->label('Total')),

                TextColumn::make('expected_closing_date')
                    ->label('Exp. Closing')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('creator.name')
                    ->label('Creator')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
