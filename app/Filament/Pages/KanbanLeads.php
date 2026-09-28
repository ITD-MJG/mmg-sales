<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Leads\Schemas\LeadForm;
use App\Filament\Traits\HasVisibilityScope;
use App\Models\Lead;
use Filament\Actions\CreateAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\BoardPage;
use Relaticle\Flowforge\Column;

class KanbanLeads extends BoardPage
{
    use HasVisibilityScope;

    protected static ?string $navigationLabel = 'Lead Board';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 2;

    public function getHeading(): string
    {
        return 'Lead Board';
    }

    public function board(Board $board): Board
    {
        return $board
            ->query($this->getEloquentQuery())
            ->recordTitleAttribute('title')
            ->columnIdentifier('status')
            ->positionIdentifier('position')
            ->cardSchema(fn (Schema $schema) => $schema
                ->components([
                    TextEntry::make('customer_name')
                        ->label('')
                        ->size(TextSize::Small)
                        ->icon('heroicon-m-building-office')
                        ->color('gray'),

                    Group::make([
                        TextEntry::make('assignedUser.name')
                            ->label('')
                            ->badge()
                            ->icon('heroicon-m-user')
                            ->color('gray')
                            ->placeholder('Unassigned'),

                        TextEntry::make('estimated_value')
                            ->label('')
                            ->badge()
                            ->money('IDR')
                            ->icon('heroicon-m-banknotes')
                            ->color('success'),
                    ])
                        ->columns(1)
                        ->extraAttributes(['class' => 'flex flex-wrap gap-2']),

                    TextEntry::make('latestActivity.subject')
                        ->label('Last Activity')
                        ->placeholder('No activity yet')
                        ->color('gray')
                        ->size(TextSize::ExtraSmall),
                ])
            )
            ->columns([
                Column::make('new')->label('New')->color('gray'),
                Column::make('contacted')->label('Contacted')->color('blue'),
                Column::make('qualified')->label('Qualified')->color('warning'),
                Column::make('proposal')->label('Proposal')->color('info'),
                Column::make('negotiation')->label('Negotiation')->color('primary'),
                Column::make('won')->label('Won')->color('success'),
                Column::make('lost')->label('Lost')->color('danger'),
            ]);
    }

    public function getEloquentQuery(): Builder
    {
        $query = Lead::query()->with(['latestActivity', 'assignedUser']);

        $user = auth()->user();

        // Same visibility contract as LeadsTable::configure()
        self::applyVisibilityScope($query, 'created_by');

        // Include leads where user is a collaborator (skip for global viewers,
        // whose scope adds no WHERE and would be swallowed by this top-level OR)
        if ($user && ! $user->hasGlobalVisibility()) {
            $query->orWhereHas('collaborators', fn ($q) => $q->where('users.id', $user->id));
        }

        return $query;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->model(Lead::class)
                ->form(fn (Schema $schema) => LeadForm::configure($schema)->getComponents())
                ->successRedirectUrl(fn (Lead $record): string => route('filament.admin.resources.leads.edit', $record)),
        ];
    }
}
