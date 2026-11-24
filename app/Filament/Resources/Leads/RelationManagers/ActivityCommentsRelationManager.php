<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use App\Models\Activity;
use App\Models\ActivityComment;
use App\Models\Lead;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;

class ActivityCommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'activityComments';

    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Only users attached to the lead (its creator) with the create permission
     * may comment, and only on activities they belong to.
     */
    protected function getCreateAuthorizationResponse(): Response
    {
        $user = auth()->user();
        $lead = $this->getOwnerRecord();

        $allowed = $user?->can('create_activity_comment')
            && $lead instanceof Lead
            && ($user->hasGlobalVisibility() || $lead->created_by === $user->id || in_array($lead->created_by, $user->subordinateUserIds(), true));

        return $allowed ? Response::allow() : Response::deny();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('activity_id')
                    ->label('Activity')
                    ->relationship('activity', 'subject', fn ($query) => $query->accessibleBy(auth()->user()))
                    ->searchable()
                    ->required()
                    // The activity is chosen once, when the comment is created,
                    // and authorised against the create rules at that moment.
                    // Editing a comment rewrites its text: the stored activity
                    // is neither re-checked nor swappable, so a comment stays
                    // editable by its author even after they lose their link to
                    // the activity.
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->rule(fn () => function (string $attribute, $value, \Closure $fail): void {
                        $activity = Activity::find($value);

                        if (! $activity?->isAccessibleBy(auth()->user())) {
                            $fail('You can only comment on activities you belong to.');
                        }
                    }),
                Placeholder::make('activity_summary')
                    ->label('Activity')
                    ->content(fn (?ActivityComment $record): string => $record?->activity
                        ? $record->activity->activity_code.' - '.$record->activity->subject
                        : '-')
                    ->visible(fn (string $operation): bool => $operation === 'edit'),
                Textarea::make('comment')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('comment')
            ->columns([
                TextColumn::make('activity.activity_code')
                    ->label('Activity')
                    ->description(fn (ActivityComment $record): ?string => $record->activity?->subject)
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Author')
                    ->searchable(),
                TextColumn::make('comment')
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Posted')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(fn (array $data) => array_merge($data, [
                        'user_id' => auth()->id(),
                    ])),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
