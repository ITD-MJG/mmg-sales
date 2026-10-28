<?php

namespace App\Filament\Resources\Activities\Tables;

use App\Exports\ActivitiesExport;
use App\Enums\Role;
use App\Filament\Traits\HasVisibilityScope;
use App\Models\Activity;
use App\Models\ActivityComment;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ActivitiesTable
{
    use HasVisibilityScope;

    /**
     * Records the index page lists, and therefore the set Prev/Next steps
     * through. Both callers share this so navigation cannot drift from the list.
     */
    public static function listVisibilityQuery(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user) {
            return $query;
        }

        if ($user->department?->name === 'Marketing') {
            return $query->whereHas('lead');
        }

        // Grouped so the visibility predicate stays one unit: callers append
        // their own `where` (ordering, navigation), and an ungrouped orWhere
        // would let `A OR B AND extra` narrow to `A OR (B AND extra)`.
        return $query->where(function (Builder $query) use ($user): void {
            // Apply base visibility scope (user_id-based filtering)
            self::applyVisibilityScope($query, 'user_id');

            $query->orWhereHas('attendees', fn (Builder $attendees): Builder => $attendees->whereKey($user->id));

            $visibleOwnerIds = array_merge([$user->id], $user->subordinateUserIds());
            if ($user->hasAnyRole(Role::names(Role::territoryScoped())) && $user->territory_id) {
                $visibleOwnerIds = User::active()->where('territory_id', $user->territory_id)->pluck('id')->all();
            }
            $leadIds = DB::table('leads')->whereIn('created_by', $visibleOwnerIds)->pluck('id');

            if ($leadIds->isNotEmpty()) {
                $query->orWhereIn('lead_id', $leadIds->unique()->all());
            }
        });
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                self::listVisibilityQuery($query);

                $query->with('attendees:id,name');

                return $query
                    ->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [auth()->id()])
                    ->orderByDesc('performed_at');
            })
            ->columns([
                TextColumn::make('activity_code')
                    ->label('Activity Code')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('lead.lead_code')
                    ->label('Lead Code')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('performed_at')
                    ->label('Date')
                    ->date('d M Y')
                    ->formatStateUsing(fn ($state) => strtoupper(Carbon::parse($state)->translatedFormat('d M Y')))
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable(),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn ($state) => match ($state) {
                        'Online Meeting', 'In-person Meeting' => 'success',
                        'Call', 'Messaging' => 'info',
                        'Demo', 'Presentation' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('lead.creator.name')
                    ->label('Lead Creator')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Activity Creator')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('attendees')
                    ->label('Assigned To')
                    ->state(fn (Activity $record): ?string => $record->attendees->first()?->name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('attendees', fn (Builder $attendees): Builder => $attendees->where('users.name', 'like', "%{$search}%")))
                    ->formatStateUsing(fn (?string $state, Activity $record): ?string => $state === null ? null : $state.($record->attendees->count() > 1 ? ' + '.($record->attendees->count() - 1) : ''))
                    ->tooltip(fn (Activity $record): ?string => $record->attendees->count() > 1 ? $record->attendees->skip(1)->pluck('name')->implode(', ') : null)
                    ->placeholder('Unassigned')
                    ->toggleable(),

                TextColumn::make('outcome')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'Interested' => 'success',
                        'Not Interested' => 'danger',
                        'No Answer' => 'warning',
                        'Need more info' => 'info',
                        default => 'gray',
                    }),

                // The search query is attached to the count column because
                // `comments_count` is a subquery alias with no real column to
                // LIKE against; without it Filament would search a column that
                // does not exist. An activity can carry several comments by
                // several authors, so matching is `whereHas` rather than an
                // equality on a single author.
                TextColumn::make('comments_count')
                    ->label('Comments')
                    ->counts('comments')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'comments.user',
                        fn (Builder $users): Builder => $users->where('name', 'like', "%{$search}%"),
                    )),
            ])
            ->filters([
                SelectFilter::make('customer')
                    ->label('Customer')
                    ->relationship('customer', 'name', fn ($query) => $query->orderBy('name'))
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                Action::make('exportExcel')
                    ->label('Export to Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($query) {
                        return Excel::download(
                            new ActivitiesExport($query),
                            'activities-export-'.now()->format('Y-m-d').'.xlsx'
                        );
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (Activity $record) => self::canModifyRecord($record, 'user_id')),
                Action::make('addComment')
                    ->label('Add Comment')
                    ->icon('heroicon-m-chat-bubble-left')
                    ->color('info')
                    ->form([
                        Textarea::make('comment')
                            ->label('Comment')
                            ->required()
                            ->rows(3)
                            ->maxLength(5000),
                    ])
                    ->action(function (Activity $record, array $data): void {
                        $record->comments()->create([
                            'user_id' => auth()->id(),
                            'comment' => $data['comment'],
                        ]);
                    })
                    ->visible(fn (Activity $record) => auth()->user()?->can('createForActivity', [ActivityComment::class, $record])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->hasRole('Super Admin')),
                ]),
            ]);
    }
}
