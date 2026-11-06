<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\LeadStatusResolver;
use Illuminate\Console\Command;

/**
 * Replays the derivation rule over every lead and writes back the status its
 * activities justify.
 *
 * The rule lives in LeadStatusResolver, which is also what the drift report
 * reads, so this command cannot drift from the observer that runs on new
 * activities. It only ever touches `in_process` and `new`: every later stage is
 * a manual move, and `closed_lost` is a human judgement recorded by an activity.
 */
class SyncLeadStatuses extends Command
{
    protected $signature = 'leads:sync-statuses {--dry-run : Preview changes without updating}';

    protected $description = 'Rewrite lead statuses to the value their activities justify';

    public function handle(LeadStatusResolver $resolver): int
    {
        $leads = Lead::query()
            ->whereIn('status', ['new', 'in_process'])
            ->with('activities:id,lead_id,outcome')
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($leads as $lead) {
            $newStatus = $resolver->derive($lead);

            if ($newStatus === $lead->status) {
                $skipped++;

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("  {$lead->lead_code}: {$lead->status} → {$newStatus}");
            } else {
                $lead->update(['status' => $newStatus]);
            }

            $updated++;
        }

        $this->info($this->option('dry-run')
            ? "Dry run: {$updated} would change, {$skipped} unchanged."
            : "Done: {$updated} updated, {$skipped} unchanged.");

        return Command::SUCCESS;
    }
}
