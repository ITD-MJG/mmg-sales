<?php

namespace App\Observers;

use App\Models\Activity;
use App\Models\Opportunity;

class ActivityObserver
{
    /**
     * Handle the Activity "created" event.
     *
     * The pipeline stages (qualified → won/lost) live on the opportunity. The
     * thin `leads` table is raw intake only and its enum stops at
     * new/contacted/converted/disqualified, so a lead is never pushed into a
     * pipeline stage here.
     */
    public function created(Activity $activity): void
    {
        $opportunity = $activity->opportunity;

        if ($opportunity) {
            $this->advanceOpportunityStage($opportunity, $activity);

            return;
        }

        $lead = $activity->lead;

        if (! $lead) {
            return;
        }

        // Update the last contacted timestamp
        $lead->last_contacted_at = $activity->performed_at ?? now();

        if ($this->isHardStop($activity)) {
            // Hard stop: the lead is disqualified, not "lost" — losing is a
            // pipeline outcome that only exists on the opportunity.
            $lead->status = 'disqualified';
            $lead->disqualified_at = now();
        } elseif ($lead->status === 'new') {
            // New -> Contacted (any activity)
            $lead->status = 'contacted';
        }

        $lead->save();
    }

    /**
     * Advance the opportunity's stage from the activity, never regressing it.
     */
    private function advanceOpportunityStage(Opportunity $opportunity, Activity $activity): void
    {
        $opportunity->last_contacted_at = $activity->performed_at ?? now();

        if ($this->isHardStop($activity)) {
            $opportunity->stage = 'lost';
            $opportunity->closed_at = now();

            $opportunity->save();

            return;
        }

        $type = strtolower($activity->type ?? '');
        $subject = strtolower($activity->subject ?? '');

        $isMeeting = str_contains($type, 'presentation')
            || str_contains($type, 'demo')
            || str_contains($type, 'meeting');

        $isProposal = str_contains($subject, 'proposal')
            || str_contains($subject, 'quote')
            || str_contains($subject, 'kuotasi')
            || str_contains($subject, 'penawaran');

        if ($opportunity->stage === 'new') {
            $opportunity->stage = 'contacted';
        }

        if ($opportunity->stage === 'contacted' && $isMeeting) {
            $opportunity->stage = 'qualified';
        }

        if (in_array($opportunity->stage, ['contacted', 'qualified'], true) && $isProposal) {
            $opportunity->stage = 'proposal';
        }

        $opportunity->save();
    }

    private function isHardStop(Activity $activity): bool
    {
        $outcome = strtolower($activity->outcome ?? '');

        return $activity->outcome === 'Not Interested'
            || in_array($outcome, ['tidak tertarik', 'batal', 'gagal'], true);
    }

    /**
     * Handle the Activity "updated" event.
     */
    public function updated(Activity $activity): void
    {
        //
    }

    /**
     * Handle the Activity "deleted" event.
     */
    public function deleted(Activity $activity): void
    {
        //
    }

    /**
     * Handle the Activity "restored" event.
     */
    public function restored(Activity $activity): void
    {
        //
    }

    /**
     * Handle the Activity "force deleted" event.
     */
    public function forceDeleted(Activity $activity): void
    {
        //
    }
}
