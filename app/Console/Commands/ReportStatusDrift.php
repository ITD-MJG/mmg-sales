<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\LeadStatusResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reports records whose stored status cannot be justified by their own history.
 *
 * Two independent questions, because the halves of the pipeline fail
 * differently:
 *
 *  - Leads: the status is derivable, so drift is a rule mismatch the resolver
 *    can name exactly.
 *  - Opportunities: the stage is hand-typed, but whether it was ever *changed*
 *    is answerable from activity_log — a record whose stage has no logged
 *    change has no history behind it either way.
 *
 * The capability line is the one that matters. If attribute logging is still
 * off, every transition count is zero and the drift numbers below are a
 * snapshot of the present with no past, which is the thing to fix first.
 */
class ReportStatusDrift extends Command
{
    protected $signature = 'leads:report-drift
        {--show=20 : How many drifted leads to list}';

    protected $description = 'Report leads whose status is not justified by their activities';

    public function handle(LeadStatusResolver $resolver): int
    {
        $this->reportLoggingCapability();

        $this->reportLeadDrift($resolver);

        return Command::SUCCESS;
    }

    /**
     * Whether activity_log can answer "how did this get here?" at all.
     */
    private function reportLoggingCapability(): void
    {
        $leads = DB::table('activity_log')->where('subject_type', Lead::class);

        $this->line('<options=bold>Attribute logging</>');

        // Spatie v5 keeps the model diff in attribute_changes. `properties` is
        // the caller-supplied bag and stays `[]` for model events, so reading
        // it makes a working log look blind.
        $this->line(sprintf(
            '  %d log rows, %d carrying attribute changes',
            (clone $leads)->count(),
            (clone $leads)->whereNotNull('attribute_changes')->where('attribute_changes', '!=', '[]')->count(),
        ));

        // Logging works; what it lacks is history. Every lead row predates the
        // first diff, so the log answers "how did this get here?" only for
        // changes made from now on.
        if ($leads->whereNotNull('attribute_changes')->where('attribute_changes', '!=', '[]')->doesntExist()) {
            $this->newLine();
            $this->warn('  No lead change has ever been recorded.');
            $this->line('  Transition history starts from the next status change, not from the records existing today.');
        }

        $this->newLine();
    }

    private function reportLeadDrift(LeadStatusResolver $resolver): void
    {
        $this->line('<options=bold>Lead status drift</>');

        $leads = Lead::query()->withCount('activities')->get();

        $byStatus = [];
        $strictDrifted = 0;
        $lenientDrifted = 0;
        $drifted = [];
        $noEvidence = 0;

        foreach ($leads as $lead) {
            $strict = $resolver->derive($lead);
            $lenient = $resolver->deriveFromAnyActivity($lead);

            $byStatus[$lead->status] ??= ['total' => 0, 'strict' => 0, 'lenient' => 0];
            $byStatus[$lead->status]['total']++;

            if ($lead->activities_count === 0) {
                $noEvidence++;
            }

            if ($strict !== $lead->status) {
                $strictDrifted++;
                $byStatus[$lead->status]['strict']++;
                $drifted[] = [$lead->lead_code, $lead->status, $strict, $lead->activities_count];
            }

            if ($lenient !== $lead->status) {
                $lenientDrifted++;
                $byStatus[$lead->status]['lenient']++;
            }
        }

        $this->table(
            ['Stored status', 'Leads', 'Strict drift', 'Lenient drift'],
            collect($byStatus)->map(fn (array $row, string $status): array => [
                $status,
                $row['total'],
                $row['strict'],
                $row['lenient'],
            ])->values()->all(),
        );

        $this->line('  strict  = contact requires an outcome proving the contact was reached');
        $this->line('  lenient = any activity counts, which is what ActivityObserver does today');
        $this->newLine();

        $this->line(sprintf(
            '  %d of %d leads drifted under strict, %d under lenient.',
            $strictDrifted,
            $leads->count(),
            $lenientDrifted,
        ));
        $this->line(sprintf(
            '  %d leads have no activities at all, so neither rule can justify any status.',
            $noEvidence,
        ));

        if ($drifted !== []) {
            $this->newLine();
            $this->line('  Strict drift sample (lead, stored, derived, activities):');

            foreach (array_slice($drifted, 0, (int) $this->option('show')) as $row) {
                $this->line(sprintf('    %s  %s -> %s  (%d)', ...$row));
            }
        }

        $this->newLine();
    }
}
