<?php

namespace App\Filament\Traits;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Prev/Next header actions that step through the same records the resource's
 * index page lists, in code order.
 *
 * The page supplies the list query, so navigation honours record visibility
 * instead of walking every row in the table.
 */
trait HasRecordNavigation
{
    /**
     * The query Prev/Next steps through — the resource's list query, so the
     * sequence matches what the user sees on the index page.
     */
    abstract protected function navigationQuery(): Builder;

    /** Column the sequence is ordered by, ascending. */
    abstract protected function navigationSortColumn(): string;

    protected function previousRecordAction(): Action
    {
        return $this->recordNavigationAction('previousRecord', 'Prev', Heroicon::OutlinedArrowLeft, 'previous');
    }

    protected function nextRecordAction(): Action
    {
        return $this->recordNavigationAction('nextRecord', 'Next', Heroicon::OutlinedArrowRight, 'next');
    }

    private function recordNavigationAction(string $name, string $label, Heroicon $icon, string $direction): Action
    {
        $target = $this->adjacentRecord($direction);
        $column = $this->navigationSortColumn();

        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->outlined()
            ->disabled($target === null)
            ->tooltip($target?->getAttribute($column))
            ->url($target ? static::getResource()::getUrl('view', ['record' => $target]) : null);
    }

    /**
     * The record immediately before or after the current one in code order,
     * scoped to the records the user may view.
     */
    private function adjacentRecord(string $direction): ?Model
    {
        $record = $this->getRecord();
        $column = $this->navigationSortColumn();
        $key = $record->getKeyName();
        $isPrevious = $direction === 'previous';
        $comparison = $isPrevious ? '<' : '>';
        $order = $isPrevious ? 'desc' : 'asc';

        return $this->navigationQuery()
            ->where(function (Builder $query) use ($record, $column, $key, $comparison): void {
                $query->where($column, $comparison, $record->getAttribute($column))
                    ->orWhere(function (Builder $query) use ($record, $column, $key, $comparison): void {
                        // Equal codes fall back to the primary key, keeping the
                        // order stable for records that share one.
                        $query->where($column, $record->getAttribute($column))
                            ->where($key, $comparison, $record->getKey());
                    });
            })
            ->orderBy($column, $order)
            ->orderBy($key, $order)
            ->first();
    }
}
