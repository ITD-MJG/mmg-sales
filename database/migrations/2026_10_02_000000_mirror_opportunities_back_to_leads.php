<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors every opportunity back into the thin `leads` table.
 *
 * The CRM rework (M1/M2) renamed the old `leads` table to `opportunities` and
 * rebuilt `leads` as raw intake. Until the opportunity pipeline is validated,
 * the business keeps working out of Leads, so this migration copies every
 * opportunity back and re-points the activity trail at the mirrored lead.
 *
 * LOSSY BY DESIGN — `opportunities` has deal fields the thin `leads` table
 * cannot hold. They are dropped on the way across:
 *
 *   estimated_value, estimated_revenue, estimated_completion_date,
 *   confidence_level, closed_at, position
 *
 * `stage` is flattened into the 4-value lead status enum:
 *
 *   new                                -> new
 *   contacted, qualified, proposal,
 *   negotiation                        -> contacted
 *   won                                -> converted   (converted_at = closed_at ?? updated_at)
 *   lost                               -> disqualified (disqualified_at = updated_at)
 *
 * This migration is ADDITIVE: it never deletes an opportunity, and never
 * removes `opportunities` or its rows. Both tables hold the data afterwards,
 * which is the point of a safety rollback. Idempotent — a lead is keyed by its
 * mirrored `lead_code`, so re-running skips opportunities already copied.
 *
 * Codes carry over unchanged. M1 renamed `lead_code` -> `opportunity_code` but
 * kept the values, so a production opportunity is already coded `LEAD-202605-…`
 * and needs no rewriting.
 */
return new class extends Migration
{
    /**
     * Reuse the opportunity's own code wherever possible.
     *
     * M1 renamed the column `lead_code` -> `opportunity_code` but kept the
     * values, so a production opportunity is coded `LEAD-202605-0001` — the
     * original lead code. Those are copied verbatim, which is what makes the
     * mirror a true rollback rather than a renaming.
     *
     * The other two branches are defensive: `OPP-` for environments that
     * adopted the new prefix, and a derived fallback so a row can always be
     * keyed even if its code is null or an unknown shape.
     */
    private function mirroredLeadCode(?string $opportunityCode, int $opportunityId): string
    {
        $code = (string) $opportunityCode;

        if (str_starts_with($code, 'LEAD-')) {
            return $code;
        }

        if (str_starts_with($code, 'OPP-')) {
            return 'LEAD-'.substr($code, 4);
        }

        return 'LEAD-OPP-'.$opportunityId;
    }

    private function leadStatusFor(string $stage): string
    {
        return match ($stage) {
            'won' => 'converted',
            'lost' => 'disqualified',
            'contacted', 'qualified', 'proposal', 'negotiation' => 'contacted',
            default => 'new',
        };
    }

    public function up(): void
    {
        if (! Schema::hasTable('opportunities') || ! Schema::hasTable('leads')) {
            return;
        }

        // opportunity id => mirrored lead id, built as we copy.
        $leadIdByOpportunity = [];

        DB::table('opportunities')->orderBy('id')->chunkById(200, function ($opportunities) use (&$leadIdByOpportunity): void {
            $rows = [];
            $now = now();

            foreach ($opportunities as $opportunity) {
                $code = $this->mirroredLeadCode((string) $opportunity->opportunity_code, (int) $opportunity->id);

                // Already mirrored by an earlier run.
                $existing = DB::table('leads')->where('lead_code', $code)->value('id');

                if ($existing) {
                    $leadIdByOpportunity[$opportunity->id] = $existing;

                    continue;
                }

                $rows[] = [
                    'lead_code' => $code,
                    'title' => $opportunity->title,
                    'customer_name' => $opportunity->customer_name,
                    'customer_id' => $opportunity->customer_id,
                    'contact_person' => null,
                    'email' => $opportunity->email,
                    'phone' => $opportunity->phone,
                    'status' => $this->leadStatusFor((string) $opportunity->stage),
                    'source' => $opportunity->source,
                    'priority' => $opportunity->priority,
                    'notes' => $opportunity->notes,
                    'assigned_to' => $opportunity->assigned_to,
                    'created_by' => $opportunity->created_by,
                    'converted_at' => $opportunity->stage === 'won'
                        ? ($opportunity->closed_at ?? $opportunity->updated_at ?? $now)
                        : $opportunity->converted_at,
                    'disqualified_at' => $opportunity->stage === 'lost'
                        ? ($opportunity->updated_at ?? $now)
                        : null,
                    'last_contacted_at' => $opportunity->last_contacted_at,
                    'created_at' => $opportunity->created_at ?? $now,
                    'updated_at' => $opportunity->updated_at ?? $now,
                ];
            }

            if ($rows === []) {
                return;
            }

            DB::table('leads')->insert($rows);

            // Resolve the ids we just inserted, keyed back to the opportunity.
            $codes = array_column($rows, 'lead_code');
            $inserted = DB::table('leads')->whereIn('lead_code', $codes)->pluck('id', 'lead_code');

            foreach ($opportunities as $opportunity) {
                $code = $this->mirroredLeadCode((string) $opportunity->opportunity_code, (int) $opportunity->id);

                if (isset($inserted[$code])) {
                    $leadIdByOpportunity[$opportunity->id] = (int) $inserted[$code];
                }
            }
        });

        $this->moveActivitiesOntoLeads($leadIdByOpportunity);
    }

    /**
     * Activities carry both columns; the Activity model refuses a row that has
     * both, so the move is a swap, not a copy. The opportunity trail is
     * reachable through the mirrored lead afterwards.
     *
     * @param  array<int, int>  $leadIdByOpportunity
     */
    private function moveActivitiesOntoLeads(array $leadIdByOpportunity): void
    {
        if (! Schema::hasColumn('activities', 'lead_id')) {
            return;
        }

        foreach ($leadIdByOpportunity as $opportunityId => $leadId) {
            DB::table('activities')
                ->where('opportunity_id', $opportunityId)
                ->update([
                    'lead_id' => $leadId,
                    'opportunity_id' => null,
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('leads') || ! Schema::hasTable('opportunities')) {
            return;
        }

        // Reverse the re-points first so the mirrored leads are no longer referenced.
        foreach (DB::table('opportunities')->orderBy('id')->pluck('opportunity_code', 'id') as $opportunityId => $code) {
            $leadId = DB::table('leads')
                ->where('lead_code', $this->mirroredLeadCode((string) $code, (int) $opportunityId))
                ->value('id');

            if (! $leadId) {
                continue;
            }

            if (Schema::hasColumn('activities', 'lead_id')) {
                DB::table('activities')
                    ->where('lead_id', $leadId)
                    ->update([
                        'opportunity_id' => $opportunityId,
                        'lead_id' => null,
                    ]);
            }

        }

        // Drop only the rows this migration created — never a pre-existing lead.
        foreach (DB::table('opportunities')->orderBy('id')->get(['id', 'opportunity_code']) as $opportunity) {
            DB::table('leads')
                ->where('lead_code', $this->mirroredLeadCode((string) $opportunity->opportunity_code, (int) $opportunity->id))
                ->delete();
        }
    }
};
