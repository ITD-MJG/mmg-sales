<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Filament\Traits\HasVisibilityScope;
use App\Models\Activity;
use App\Models\Lead;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeadsTable
{
    use HasVisibilityScope;

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();

                // Role-based visibility: staff sees own, managers see subordinates, etc.
                self::applyVisibilityScope($query, 'created_by');

                // Also include leads where the user is a collaborator (skip for global viewers,
                // whose scope adds no WHERE and would be swallowed by this top-level OR)
                if ($user && ! $user->hasGlobalVisibility()) {
                    $query->orWhereHas('collaborators', fn ($q) => $q->where('users.id', $user->id));
                }

                // Sort by latest activity on the lead (most recently worked leads first)
                return $query->orderByDesc(
                    Activity::query()
                        ->whereColumn('activities.lead_id', 'leads.id')
                        ->selectRaw('MAX(performed_at)')
                );
            })
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

                TextColumn::make('contactPerson.name')
                    ->label('Contact Person')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('creator.name')
                    ->label('Creator')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('assignedCollaborators')
                    ->label('Assigned To')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('collaborators', fn ($q) => $q->where('name', 'like', "%{$search}%")))
                    ->getStateUsing(function ($record): string {
                        $names = $record->collaborators->pluck('name')->filter()->values();

                        if ($names->isEmpty()) {
                            return '-';
                        }

                        if ($names->count() === 1) {
                            return $names->first();
                        }

                        return $names->first().' + '.($names->count() - 1).' others';
                    })
                    ->tooltip(function ($record): ?string {
                        $names = $record->collaborators->pluck('name')->filter();

                        return $names->isEmpty() ? null : $names->join(', ');
                    })
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

                TextColumn::make('estimated_value')
                    ->label('Estimated Value')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('estimated_revenue')
                    ->label('Expected Revenue')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('estimated_completion_date')
                    ->label('Est. Finish')
                    ->date('M Y')
                    ->formatStateUsing(fn ($state) => $state ? strtoupper(Carbon::parse($state)->translatedFormat('M Y')) : '-')
                    ->sortable()
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
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (Lead $record) => self::canModifyRecord($record, 'created_by')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->hasAnyRole(['Super Admin', 'Sales Staff', 'Marketing Staff', 'Logistics Staff', 'Finance & Accounting Staff', 'Sales Supervisor', 'Import & Purchasing Supervisor', 'Sales Regional Manager', 'Sales Area Manager'])),
                    ForceDeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->hasRole('Super Admin')),
                    RestoreBulkAction::make()
                        ->visible(fn () => auth()->user()?->hasRole('Super Admin')),
                ]),
            ]);
    }
}
