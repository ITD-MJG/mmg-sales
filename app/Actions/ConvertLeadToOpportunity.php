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
     * same opportunity rather than creating a second one.
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

        $existing = Opportunity::where('converted_from_lead_id', $lead->id)->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($lead): Opportunity {
            $opportunity = Opportunity::create([
                'title' => $lead->title,
                'customer_id' => $lead->customer_id,
                'customer_name' => $lead->customer_name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'stage' => 'qualified',
                'source' => $lead->source,
                'priority' => $lead->priority,
                'notes' => $lead->notes,
                'assigned_to' => $lead->assigned_to,
                'created_by' => $lead->created_by,
                'converted_from_lead_id' => $lead->id,
                'converted_at' => now(),
            ]);

            Activity::where('lead_id', $lead->id)
                ->update([
                    'lead_id' => null,
                    'opportunity_id' => $opportunity->id,
                ]);

            $lead->update([
                'status' => 'converted',
                'converted_at' => now(),
            ]);

            return $opportunity;
        });
    }
}
