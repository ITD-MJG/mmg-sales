<?php

namespace App\Console\Commands;

use App\DTOs\ReportFilterData;
use App\Exports\PipelineReportExport;
use App\Exports\SalesReportExport;
use App\Mail\ReportDigestMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class SendReportDigest extends Command
{
    protected $signature = 'reports:send-digest
        {--period=weekly : daily|weekly}
        {--role=Management Director : Role to target}';

    protected $description = 'Email scheduled report digests to role-based recipients';

    public function handle(): int
    {
        $period = $this->option('period');
        $role = $this->option('role');

        $users = User::role($role)->get();

        if ($users->isEmpty()) {
            $this->warn("No users found with role '{$role}'");

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($users as $user) {
            $filterData = $this->filterForUser($user, $role);

            $slug = strtolower(str_replace(' ', '_', $role));
            $timestamp = now()->format('Ymd_His');

            $salesFilename = 'digest_sales_'.$slug.'_'.$user->id.'_'.$timestamp.'.xlsx';
            $pipelineFilename = 'digest_pipeline_'.$slug.'_'.$user->id.'_'.$timestamp.'.xlsx';

            Excel::store(new SalesReportExport($filterData), 'reports/'.$salesFilename, 'local');
            Excel::store(new PipelineReportExport($filterData), 'reports/'.$pipelineFilename, 'local');

            $attachmentPaths = [
                Storage::disk('local')->path('reports/'.$salesFilename),
                Storage::disk('local')->path('reports/'.$pipelineFilename),
            ];

            Mail::to($user->email)->send(new ReportDigestMail(
                period: $period,
                userName: $user->name,
                attachmentPaths: $attachmentPaths,
            ));

            $sent++;
        }

        $this->info("Sent {$period} digest to {$sent} user(s) with role '{$role}'");

        return self::SUCCESS;
    }

    protected function filterForUser(User $user, string $role): ReportFilterData
    {
        $filters = [
            'start_date' => now()->startOfMonth()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ];

        if (in_array($role, ['Regional Sales Manager', 'Area Sales Manager'])) {
            $filters['territory_id'] = $user->territory_id;
        } elseif (in_array($role, ['Sales Supervisor', 'Sales Staff'])) {
            $filters['user_id'] = $user->id;
        }

        return ReportFilterData::fromArray($filters);
    }
}
