<?php

namespace App\Actions;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\Opportunity;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConvertLeadToOpportunity
{
    /**
     * Promote a raw lead into a qualified opportunity.
     *
     * Idempotent: a lead that already produced an opportunity returns that
     * same opportunity rather than creating a second one. The authoritative
     * check runs inside the transaction against a row lock on the lead, so two
     * overlapping calls (a double click, or a manual convert racing the
     * observer's auto-promotion) cannot both create an opportunity.
     *
     * @throws InvalidArgumentException when the lead has no customer.
     */
    public function handle(Lead $lead): Opportunity
    {
        if (blank($lead->customer_id)) {
            throw new InvalidArgumentException(
                'A lead needs a customer before it can become an opportunity.'
            );
        }

        // Fast path: the common already-converted case skips taking a row lock.
        $existing = $this->opportunityFor($lead);

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($lead): Opportunity {
            // Serialise concurrent conversions of this lead. A second call
            // blocks here until the first commits, then sees the opportunity
            // it created and returns it instead of creating a duplicate.
            $locked = Lead::whereKey($lead->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                throw new InvalidArgumentException(
                    'The lead no longer exists.'
                );
            }

            $existing = $this->opportunityFor($locked);

            if ($existing) {
                return $existing;
            }

            $opportunity = Opportunity::create([
                'title' => $locked->title,
                'customer_id' => $locked->customer_id,
                'customer_name' => $locked->customer_name,
                'email' => $locked->email,
                'phone' => $locked->phone,
                'stage' => 'qualified',
                'source' => $locked->source,
                'priority' => $locked->priority,
                'notes' => $locked->notes,
                'assigned_to' => $locked->assigned_to,
                'created_by' => $locked->created_by,
                'converted_from_lead_id' => $locked->getKey(),
                'converted_at' => now(),
            ]);

            Activity::where('lead_id', $locked->getKey())
                ->update([
                    'lead_id' => null,
                    'opportunity_id' => $opportunity->id,
                ]);

            $locked->update([
                'status' => 'converted',
                'converted_at' => now(),
            ]);

            return $opportunity;
        });
    }

    /**
     * The opportunity a lead already produced, if any.
     */
    private function opportunityFor(Lead $lead): ?Opportunity
    {
        return Opportunity::where('converted_from_lead_id', $lead->getKey())->first();
    }
}
