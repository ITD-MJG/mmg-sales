<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Filament\Actions\ConvertLeadToOpportunityAction;
use App\Filament\Traits\HasVisibilityScope;
use App\Models\Lead;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeadsTable
{
    use HasVisibilityScope;

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                if (! auth()->user()?->hasAnyRole(['Marketing Staff', 'Marketing Manager'])) {
                    self::applyVisibilityScope($query, 'created_by');
                }
                $query->with(['collaborators:id,name']);

                return $query->orderByDesc('leads.created_at');
            })
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (Lead $record) => self::canModifyRecord($record, 'created_by')),
                ConvertLeadToOpportunityAction::make(),
            ])
            ->columns([
                TextColumn::make('lead_code')
                    ->label('Lead Code')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('latestActivity.performed_at')
                    ->label('Last Contact')
                    ->date('d M Y')
                    ->description(function (Lead $record): ?string {
                        $subject = $record->latestActivity?->subject;

                        if (! $subject) {
                            return null;
                        }

                        return strlen($subject) > 16
                            ? substr($subject, 0, 16).'...'
                            : $subject;
                    })
                    ->tooltip(fn (Lead $record): ?string => $record->latestActivity?->subject)
                    ->formatStateUsing(fn ($state) => $state ? strtoupper(Carbon::parse($state)->translatedFormat('d M Y')) : '-')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->formatStateUsing(function ($state): string {
                        $state = (string) $state;

                        return strlen($state) > 32 ? substr($state, 0, 32).'...' : $state;
                    })
                    ->tooltip(fn (string $state): ?string => strlen($state) > 32 ? $state : null)
                    ->toggleable(),

                TextColumn::make('estimated_revenue')
                    ->label('Exp. Revenue')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('expected_closing_date')
                    ->label('Exp. Closing')
                    ->date('d M Y')
                    ->formatStateUsing(fn ($state) => $state ? strtoupper(Carbon::parse($state)->translatedFormat('d M Y')) : '-')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'converted' => 'success',
                        'disqualified' => 'danger',
                        'contacted' => 'info',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('creator.name')
                    ->label('Creator')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('collaborators')
                    ->label('Assigned To')
                    ->state(fn (Lead $record): ?string => $record->collaborators->first()?->name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('collaborators', fn (Builder $collaborators): Builder => $collaborators->where('users.name', 'like', "%{$search}%")))
                    ->formatStateUsing(fn (?string $state, Lead $record): ?string => $state === null ? null : $state.($record->collaborators->count() > 1 ? ' + '.($record->collaborators->count() - 1) : ''))
                    ->tooltip(fn (Lead $record): ?string => $record->collaborators->count() > 1 ? $record->collaborators->skip(1)->pluck('name')->implode(', ') : null)
                    ->placeholder('Unassigned')
                    ->toggleable(),

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

                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state)))
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('customer.name')
                    ->label('Linked Customer')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('opportunities_count')
                    ->label('Opportunities')
                    ->counts('opportunities')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('activities_count')
                    ->label('Activities')
                    ->counts('activities')
                    ->sortable()
                    ->toggleable(),

                // Comments belong to the lead's activities, not the lead, so
                // they are reached through the hasManyThrough relation. The
                // search query is attached to the count column because
                // `activity_comments_count` is a subquery alias with no real
                // column to LIKE against. A lead can carry several comments by
                // several authors, so matching is `whereHas`.
                TextColumn::make('activity_comments_count')
                    ->label('Comments')
                    ->counts('activityComments')
                    ->sortable()
                    ->toggleable()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'activityComments.user',
                        fn (Builder $users): Builder => $users->where('name', 'like', "%{$search}%"),
                    )),

                TextColumn::make('converted_at')
                    ->label('Converted')
                    ->date('d M Y')
                    ->formatStateUsing(fn ($state) => $state ? strtoupper(Carbon::parse($state)->translatedFormat('d M Y')) : '-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->hasAnyRole(['Super Admin', 'Sales Staff', 'Marketing Staff', 'Logistics Staff', 'Finance & Accounting Staff', 'Sales Supervisor', 'Import & Purchasing Supervisor', 'Regional Sales Manager', 'Area Sales Manager'])),
                    ForceDeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->hasRole('Super Admin')),
                    RestoreBulkAction::make()
                        ->visible(fn () => auth()->user()?->hasRole('Super Admin')),
                ]),
            ]);
    }
}
