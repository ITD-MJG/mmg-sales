<?php

namespace App\Filament\Resources\Activities\Tables;

use App\Exports\ActivitiesExport;
use App\Filament\Traits\HasVisibilityScope;
use App\Models\Activity;
use App\Models\ActivityComment;
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

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();

                if (! $user) {
                    return $query->orderBy('performed_at', 'desc');
                }

                // Apply base visibility scope (user_id-based filtering)
                self::applyVisibilityScope($query, 'user_id');

                // For non-Super Admin users, also include activities on leads
                // where the user is the creator or a collaborator,
                // but only if the activity's user is in the same territory.
                // Director sees all territories, so no territory constraint.
                if (! $user->hasRole('Super Admin')) {
                    $leadIds = DB::table('lead_collaborators')
                        ->where('user_id', $user->id)
                        ->pluck('lead_id')
                        ->merge(
                            DB::table('leads')
                                ->where('created_by', $user->id)
                                ->pluck('id')
                        )
                        ->unique()
                        ->toArray();

                    if (! empty($leadIds)) {
                        $query->orWhere(function ($q) use ($leadIds, $user) {
                            $q->whereIn('lead_id', $leadIds);

                            if (! $user->hasRole('Management Director') && $user->territory_id) {
                                $q->whereHas('user', function ($uq) use ($user): void {
                                    $uq->where('territory_id', $user->territory_id);
                                });
                            }
                        });
                    }
                }

                return $query->orderBy('performed_at', 'desc');
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
                    ->label('Creator')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Assignee')
                    ->searchable()
                    ->sortable(),

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

                TextColumn::make('comments_count')
                    ->label('Comments')
                    ->counts('comments')
                    ->badge()
                    ->color('info')
                    ->sortable(),
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
