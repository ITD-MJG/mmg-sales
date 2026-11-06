<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Filament\Traits\HasVisibilityScope;
use App\Models\Lead;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeadsTable
{
    use HasVisibilityScope;

    /**
     * The rows a user may see on the list, the board and the status chart.
     *
     * Leads are owned by their creator or by any collaborator, so this must use
     * `applyLeadVisibilityScope()`: the ownership-only `applyVisibilityScope()`
     * matched `created_by` alone and hid a lead from the rep it was handed to.
     * Everyone else still resolves through `visibleUserIds()`, so managers keep
     * seeing their team's leads and global viewers see everything.
     */
    public static function listVisibilityQuery(Builder $query, ?User $user): Builder
    {
        if (! $user?->isMarketingDepartment()) {
            self::applyLeadVisibilityScope($query, 'created_by');
        }

        return $query;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                self::listVisibilityQuery($query, auth()->user());
                $query->with(['collaborators:id,name']);

                return $query;
            })
            // Newest day first, which is also the order the day groups read in
            // while the group's own ordering stays out of the way.
            ->defaultSort('created_at', 'desc')
            // One header per day a lead was created, as a date separator between
            // the rows. Not offered in the grouping dropdown: the separator is
            // part of the list's shape, not a user preference.
            //
            // The separator title is written by hand rather than left to `->date()`:
            // a date group re-parses the title as a date and formats it with the
            // table's default format, which ignores `getTitleFromRecordUsing()` and
            // cannot be uppercased. `whereDate` in `scopeQueryByKeyUsing()` keeps
            // the day bucketing the `date()` flag would have done.
            ->defaultGroup(
                Group::make('created_at')
                    ->label('Created')
                    ->titlePrefixedWithLabel(false)
                    ->getKeyFromRecordUsing(fn (Lead $record): string => $record->created_at->toDateString())
                    ->getTitleFromRecordUsing(fn (Lead $record): string => strtoupper($record->created_at->translatedFormat('d M Y')))
                    ->scopeQueryByKeyUsing(fn (Builder $query, string $key): Builder => $query->whereDate('leads.created_at', $key))
                    ->orderQueryUsing(function (Builder $query): Builder {
                        // Group ordering is applied before the table's own sort and
                        // would win, pinning the list to one direction and breaking
                        // the sortable headers. Let the sort order.
                        return $query;
                    }),
            )
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (Lead $record) => self::canModifyRecord($record, 'created_by')),

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
                        'closed_won' => 'success',
                        'closed_lost' => 'danger',
                        'negotiation' => 'warning',
                        'proposal' => 'primary',
                        'qualification' => 'warning',
                        'prospecting' => 'info',
                        'in_process' => 'info',
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

                TextColumn::make('confidence_level')
                    ->label('Confidence')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : (Lead::CONFIDENCE_LABELS[$state] ?? $state))
                    ->color(fn (?string $state): string => match ($state) {
                        'low' => 'gray',
                        'medium' => 'info',
                        'high' => 'warning',
                        'very_high' => 'success',
                        'committed' => 'primary',
                        default => 'gray',
                    })
                    ->placeholder('Not assessed')
                    // Bands sort by their weight, not alphabetically: "committed"
                    // is the strongest band but sorts before "low" as a string.
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                        'CASE leads.confidence_level '
                        .collect(Lead::CONFIDENCE_WEIGHTS)
                            ->map(fn (int $weight, string $band): string => "WHEN '{$band}' THEN {$weight}")
                            ->implode(' ')
                        .' ELSE 0 END '.($direction === 'desc' ? 'desc' : 'asc')
                    ))
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
                SelectFilter::make('confidence_level')
                    ->label('Confidence')
                    ->options([
                        ...Lead::CONFIDENCE_LABELS,
                        'none' => 'Not assessed',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value']),
                        fn (Builder $query): Builder => $data['value'] === 'none'
                            ? $query->whereNull('confidence_level')
                            : $query->where('confidence_level', $data['value']),
                    )),
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
