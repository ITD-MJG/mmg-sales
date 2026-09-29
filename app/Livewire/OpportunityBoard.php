<?php

namespace App\Livewire;

use App\Filament\Traits\HasVisibilityScope;
use App\Models\Opportunity;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\Column;
use Relaticle\Flowforge\Concerns\BaseBoard;
use Relaticle\Flowforge\Contracts\HasBoard;

/**
 * Kanban board for opportunities, embedded as a tab on the opportunities list.
 *
 * A plain Livewire component rather than a Filament BoardPage, so the list page
 * can render it inside a tab without a second navigation entry. BaseBoard brings
 * the action/schema/form wiring BoardPage would otherwise supply.
 */
class OpportunityBoard extends Component implements HasActions, HasBoard, HasForms
{
    use BaseBoard;
    use HasVisibilityScope;

    protected string $view = 'livewire.opportunity-board';

    public function board(Board $board): Board
    {
        return $board
            ->query($this->getBoardQuery())
            ->recordTitleAttribute('title')
            ->columnIdentifier('stage')
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

    public function getBoardQuery(): ?Builder
    {
        $query = Opportunity::query()->with(['latestActivity', 'assignedUser']);

        $user = auth()->user();

        // Same visibility contract as OpportunitiesTable::configure()
        self::applyVisibilityScope($query, 'created_by');

        // Include opportunities where the user is a collaborator (skip for global
        // viewers, whose scope adds no WHERE and would be swallowed by this top-level OR)
        if ($user && ! $user->hasGlobalVisibility()) {
            $query->orWhereHas('collaborators', fn ($q) => $q->where('users.id', $user->id));
        }

        return $query;
    }
}
