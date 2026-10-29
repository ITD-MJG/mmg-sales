<?php

namespace App\Filament\Widgets;

use App\Models\Activity;
use App\Models\ActivityComment;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerCommentHistoryWidget extends TableWidget
{
    public ?Model $record = null;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ActivityComment::query()
                ->with(['activity', 'user'])
                ->whereHas('activity', fn (Builder $query) => $query->where('customer_id', $this->record?->getKey()))
                ->latest('created_at')
            )
            ->heading('Comment History')
            ->columns([
                Tables\Columns\TextColumn::make('activity.subject')
                    ->label('Activity')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Author'),
                Tables\Columns\TextColumn::make('comment')
                    ->limit(80)
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Create new Comment')
                    ->authorize(fn (): bool => auth()->user()?->hasPermissionTo('create_activity_comment') ?? false)
                    ->schema([
                        Select::make('activity_id')
                            ->label('Activity')
                            ->options(fn (): array => Activity::query()
                                ->where('customer_id', $this->record?->getKey())
                                ->orderByDesc('performed_at')
                                ->pluck('subject', 'id')
                                ->all())
                            ->searchable()
                            ->required(),
                        Textarea::make('comment')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (array $data): void {
                        $activity = Activity::query()
                            ->where('customer_id', $this->record?->getKey())
                            ->findOrFail($data['activity_id']);

                        abort_unless(auth()->user()?->can('createForActivity', [ActivityComment::class, $activity]), 403);

                        $activity->comments()->create([
                            'user_id' => auth()->id(),
                            'comment' => $data['comment'],
                        ]);
                    }),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-m-eye')
                    ->schema([
                        TextEntry::make('activity.subject')->label('Activity'),
                        TextEntry::make('user.name')->label('Author'),
                        TextEntry::make('created_at')->label('Created')->dateTime('d M Y H:i'),
                        TextEntry::make('comment')->columnSpanFull(),
                    ])
                    ->modalHeading('View Comment')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5);
    }
}
