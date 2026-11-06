<?php

namespace App\Services;

use App\Models\Lead;

/**
 * Derives the lead status a lead's own activities justify.
 *
 * Read-only on purpose: nothing writes these statuses back yet. Its job is to
 * answer "does this lead's stored status agree with its activities?" so the
 * drift report can quantify how far the data has wandered from the rules.
 *
 * Two rules are exposed, because the answer differs depending on whether an
 * outcome is required to count as contact:
 *
 *  - derive()         strict: only an outcome proving the contact was reached
 *                     counts. Fails when outcome is missing, which it is on
 *                     ~35% of activities.
 *  - deriveFromAnyActivity() lenient: mirrors what ActivityObserver::created
 *                     does today — any activity at all means in_process.
 *
 * The gap between them is the cost of the missing outcomes, so both are
 * reported rather than one chosen silently.
 */
class LeadStatusResolver
{
    /**
     * Outcomes that prove somebody actually reached the contact. `No Answer`
     * is deliberately absent: a touch was attempted but nothing happened.
     */
    public const REACHED_OUTCOMES = ['Interested', 'Need more info', 'Postponed'];

    /**
     * Statuses a rep sets by hand that no activity outcome can justify or
     * contradict. Derivation must leave these alone.
     *
     * @var list<string>
     */
    public const BEYOND_INTAKE = [
        'prospecting',
        'qualification',
        'proposal',
        'negotiation',
        'closed_won',
    ];

    /**
     * The status the lead should hold under the strict rule.
     */
    public function derive(Lead $lead): string
    {
        if ($terminal = $this->terminalStatus($lead)) {
            return $terminal;
        }

        return $this->hasReachedActivity($lead) ? 'in_process' : 'new';
    }

    /**
     * The status under the observer's current rule: any activity advanced the
     * lead, whatever its outcome.
     */
    public function deriveFromAnyActivity(Lead $lead): string
    {
        if ($terminal = $this->terminalStatus($lead)) {
            return $terminal;
        }

        return $this->hasAnyActivity($lead) ? 'in_process' : 'new';
    }

    /**
     * Whether the stored status disagrees with the strict derivation.
     */
    public function isDrifted(Lead $lead): bool
    {
        return $this->derive($lead) !== $lead->status;
    }

    /**
     * The states that are decided outside the outcome vocabulary, or not at all.
     *
     * `closed_lost` is driven by a hard stop, which is a genuine judgement and
     * never reversed by a later activity, matching ActivityObserver::isHardStop.
     *
     * Everything past `in_process` — prospecting, qualification, proposal,
     * negotiation, closed_won — is a human stage move that activities do not
     * contradict, so those statuses are kept as stored. Only the two the
     * outcome vocabulary can actually justify are derived, which is why a lead
     * with no activities at all also keeps its stored status.
     *
     * `in_process` is reversible: it only claims somebody was reached, and when
     * no activity supports that claim the evidence wins and the lead falls back
     * to `new`. Whichever way you rule this, the call site should own the
     * decision rather than this class — a lead's status is not a side effect of
     * being read.
     */
    private function terminalStatus(Lead $lead): ?string
    {
        if ($this->hasNotInterested($lead)) {
            return Lead::LOST_STATUS;
        }

        if (! $this->hasAnyActivity($lead)) {
            return $lead->status;
        }

        // A stage beyond intake is a deliberate move; the activity trail cannot
        // contradict it.
        if (in_array($lead->status, self::BEYOND_INTAKE, true)) {
            return $lead->status;
        }

        return null;
    }

    private function hasNotInterested(Lead $lead): bool
    {
        return $lead->activities()->where('outcome', 'Not Interested')->exists();
    }

    private function hasReachedActivity(Lead $lead): bool
    {
        return $lead->activities()->whereIn('outcome', self::REACHED_OUTCOMES)->exists();
    }

    private function hasAnyActivity(Lead $lead): bool
    {
        return $lead->activities()->exists();
    }
}
