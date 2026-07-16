<?php

namespace App\Console\Commands;

use App\Models\Applicant;
use App\Models\EnrollmentConfirmation;
use Illuminate\Console\Command;

class RepairAdmissionEnrollmentStatus extends Command
{
    protected $signature = 'admission:repair-enrollment-status {--apply : Persist repairs instead of only reporting mismatches}';

    protected $description = 'Repair applicants that have completed enrollment confirmations but are not marked enrolled.';

    public function handle(): int
    {
        $query = EnrollmentConfirmation::query()
            ->with('applicant.user')
            ->where('status', 'completed')
            ->whereHas('applicant', fn ($applicants) => $applicants->where('status', '!=', 'enrolled'));

        $mismatches = $query->get();
        $apply = (bool) $this->option('apply');

        $this->info("Enrollment status repair found {$mismatches->count()} mismatch(es).");

        foreach ($mismatches as $confirmation) {
            $applicant = $confirmation->applicant;
            if (! $applicant) {
                continue;
            }

            $label = $applicant->user?->email
                ?? $applicant->application_number
                ?? "applicant {$applicant->id}";

            $this->line("Applicant {$applicant->id} ({$label}) has completed confirmation {$confirmation->id} but status={$applicant->status}.");

            if ($apply) {
                Applicant::whereKey($applicant->id)->update(['status' => 'enrolled']);
            }
        }

        if ($apply) {
            $this->info("Repaired {$mismatches->count()} applicant status record(s).");
        } else {
            $this->warn('Dry run only. Re-run with --apply to update applicant statuses.');
        }

        return self::SUCCESS;
    }
}
