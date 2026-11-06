<?php

namespace App\Observers;

use App\Models\Activity;
use App\Models\Lead;

class ActivityObserver
{
    /**
     * Handle the Activity "created" event.
     *
     * Activities only move a lead between the two statuses the outcome
     * vocabulary can justify: nothing reached (`new`) and something reached
     * (`in_process`). Every later stage is a manual move the rep owns, and a
     * hard stop is the one judgement that overrides them.
     */
    public function created(Activity $activity): void
    {
        $lead = $activity->lead;

        if (! $lead) {
            return;
        }

        // Update the last contacted timestamp
        $lead->last_contacted_at = $activity->performed_at ?? now();

        if ($this->isHardStop($activity)) {
            // Hard stop: the lead is disqualified, not "lost" — losing is a
            // Hard stop: the one negative outcome a lead records.
            $lead->status = Lead::LOST_STATUS;
            $lead->disqualified_at = now();
        } elseif ($lead->status === 'new') {
            // New -> In Process on any activity.
            $lead->status = 'in_process';
        }

        $lead->save();
    }

    private function isHardStop(Activity $activity): bool
    {
        $outcome = strtolower($activity->outcome ?? '');

        return $activity->outcome === 'Not Interested'
            || in_array($outcome, ['tidak tertarik', 'batal', 'gagal'], true);
    }

    /**
     * Handle the Activity "saved" event.
     *
     * Confidence is owned by the lead as a band; the visit only carries a
     * snapshot of it. When a rep raises or lowers confidence after a
     * conversation, that judgement has to land back on the lead, otherwise the
     * snapshot is the only place it ever lives.
     */
    public function saved(Activity $activity): void
    {
        if (blank($activity->lead_id) || blank($activity->confidence_level)) {
            return;
        }

        if (! $activity->wasRecentlyCreated && ! $activity->wasChanged('confidence_level')) {
            return;
        }

        $lead = $activity->lead;

        if (! $lead) {
            return;
        }

        // Only the most recent activity speaks for the lead: a backdated visit
        // must not overwrite confidence set from a newer conversation.
        $hasNewerActivity = Activity::query()
            ->where('lead_id', $lead->getKey())
            ->whereKeyNot($activity->getKey())
            ->where('performed_at', '>', $activity->performed_at)
            ->exists();

        if ($hasNewerActivity) {
            return;
        }

        $band = Lead::confidenceBandForWeight($activity->confidence_level);

        if ($band === null || $lead->confidence_level === $band) {
            return;
        }

        $lead->confidence_level = $band;
        $lead->save();
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
