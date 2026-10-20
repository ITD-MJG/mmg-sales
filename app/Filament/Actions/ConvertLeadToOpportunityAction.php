<?php

namespace App\Filament\Actions;

use App\Actions\ConvertLeadToOpportunity;
use App\Models\Lead;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Promotes a raw lead into a qualified opportunity.
 *
 * Super Admin only: the Opportunities resource is locked to Super Admin, so a
 * staff member converting a lead would create a record they cannot then open.
 * Keeping the trigger admin-only means the button never appears somewhere its
 * result is unreachable.
 *
 * Intentionally has no redirect for the same reason — the opportunity is named
 * in the success notification rather than navigated to.
 */
class ConvertLeadToOpportunityAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'convertToOpportunity';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Convert to Opportunity')
            ->icon(Heroicon::OutlinedRocketLaunch)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Convert lead to opportunity')
            ->modalDescription('This creates a qualified opportunity from this lead and moves its activity trail across.')
            ->modalSubmitActionLabel('Convert')
            ->visible(fn (Lead $record): bool => self::canConvert($record))
            ->action(function (Lead $record): void {
                $opportunity = app(ConvertLeadToOpportunity::class)->handle($record);

                Notification::make()
                    ->success()
                    ->title('Opportunity created')
                    ->body("Converted {$record->lead_code} into {$opportunity->opportunity_code}.")
                    ->send();
            });
    }

    /**
     * Super Admin only, and the lead must be convertible: it needs a customer
     * (the action throws otherwise) and must not already be converted.
     */
    public static function canConvert(Lead $record): bool
    {
        if (! auth()->user()?->isSuperAdmin()) {
            return false;
        }

        return filled($record->customer_id) && ! $record->isConverted();
    }
}
