<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use App\Models\Activity;
use App\Models\Lead;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
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
     * Only users attached to the lead (creator or collaborator) with the
     * create permission may comment on its activities.
     */
    protected function getCreateAuthorizationResponse(): Response
    {
        $user = auth()->user();
        $lead = $this->getOwnerRecord();

        $allowed = $user?->hasPermissionTo('create_activity_comment')
            && $lead instanceof Lead
            && $lead->isAccessibleBy($user);

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
                    ->rule(fn () => function (string $attribute, $value, \Closure $fail): void {
                        $activity = Activity::find($value);

                        if (! $activity?->isAccessibleBy(auth()->user())) {
                            $fail('You can only comment on activities you belong to.');
                        }
                    }),
                Textarea::make('comment')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('activity.activity_code')
                    ->label('Activity'),
                TextEntry::make('user.name')
                    ->label('Author'),
                TextEntry::make('comment')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('comment')
            ->columns([
                TextColumn::make('activity.activity_code')
                    ->label('Activity')
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
