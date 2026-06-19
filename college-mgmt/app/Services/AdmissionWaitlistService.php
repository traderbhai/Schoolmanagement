<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdmissionWaitlistService
{
    public function add(Applicant $applicant, array $data): int
    {
        $this->assertApplicantEligible($applicant);

        return DB::table('admission_waitlist_entries')->insertGetId([
            'applicant_id' => $applicant->id,
            'offer_round_id' => $data['offer_round_id'] ?? null,
            'program_id' => $data['program_id'] ?? $applicant->program_id,
            'batch_id' => $data['batch_id'] ?? $applicant->batch_id,
            'rank' => $data['rank'] ?? 1,
            'category' => $data['category'] ?? $applicant->category,
            'status' => 'waiting',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function promoteNext(int $programId, int $batchId, ?User $actor = null, string $reason = 'Seat released'): ?object
    {
        $entries = DB::table('admission_waitlist_entries')
            ->where('program_id', $programId)
            ->where('batch_id', $batchId)
            ->where('status', 'waiting')
            ->orderBy('rank')
            ->get();

        $entry = null;
        $applicant = null;
        foreach ($entries as $candidate) {
            $candidateApplicant = Applicant::find($candidate->applicant_id);
            if ($candidateApplicant && $this->applicantEligible($candidateApplicant)) {
                $entry = $candidate;
                $applicant = $candidateApplicant;
                break;
            }
        }

        if (! $entry || ! $applicant) {
            return null;
        }

        DB::table('admission_waitlist_entries')->where('id', $entry->id)->update([
            'status' => 'promoted',
            'promoted_at' => now(),
            'promoted_by' => $actor?->id,
            'promotion_reason' => $reason,
            'updated_at' => now(),
        ]);

        app(AdmissionSeatControlService::class)->hold($applicant, ['program_id' => $programId, 'batch_id' => $batchId, 'expires_at' => now()->addDays(5)], $actor);

        return DB::table('admission_waitlist_entries')->where('id', $entry->id)->first();
    }

    private function assertApplicantEligible(Applicant $applicant): void
    {
        if (! $this->applicantEligible($applicant)) {
            throw ValidationException::withMessages(['applicant_id' => 'Final-state applicants cannot be added to the waitlist.']);
        }
    }

    private function applicantEligible(Applicant $applicant): bool
    {
        return ! in_array($applicant->status, ['rejected', 'withdrawn', 'enrolled'], true);
    }
}
