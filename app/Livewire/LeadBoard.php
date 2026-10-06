<?php

namespace App\Livewire;

use App\Filament\Traits\HasVisibilityScope;
use App\Models\Lead;
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
 * Kanban board for leads, embedded as a tab on the leads list.
 *
 * A plain Livewire component rather than a Filament BoardPage, so the list page
 * can render it inside a tab without a second navigation entry. BaseBoard brings
 * the action/schema/form wiring BoardPage would otherwise supply.
 */
class LeadBoard extends Component implements HasActions, HasBoard, HasForms
{
    use BaseBoard;
    use HasVisibilityScope;

    protected string $view = 'livewire.lead-board';

    public function board(Board $board): Board
    {
        return $board
            ->query($this->getBoardQuery())
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

                        TextEntry::make('priority')
                            ->label('')
                            ->badge()
                            ->icon('heroicon-m-flag')
                            ->color(fn (?string $state): string => match ($state) {
                                'urgent' => 'danger',
                                'high' => 'warning',
                                'medium' => 'info',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (?string $state): ?string => $state ? ucfirst($state) : null),
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
                Column::make('converted')->label('Converted')->color('success'),
                Column::make('disqualified')->label('Disqualified')->color('danger'),
            ]);
    }

    public function getBoardQuery(): ?Builder
    {
        $query = Lead::query()->with(['latestActivity', 'assignedUser']);

        // Same visibility contract as LeadsTable::configure()
        self::applyVisibilityScope($query, 'created_by');

        return $query;
    }
}
