<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\EnrollmentConfirmation;
use App\Models\RequiredDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdmissionHandoffService
{
    public function ensure(Applicant $applicant, ?EnrollmentConfirmation $confirmation = null, ?User $actor = null): object
    {
        $confirmation ??= $applicant->enrollmentConfirmation;
        $summary = $this->evaluate($applicant, $confirmation);

        DB::table('admission_handoff_records')->updateOrInsert(
            ['applicant_id' => $applicant->id],
            [
                'student_id' => $confirmation?->student_id,
                'enrollment_confirmation_id' => $confirmation?->id,
                'status' => $summary['status'],
                'blockers' => json_encode($summary['blockers']),
                'verified_document_summary' => json_encode($summary['documents']),
                'fee_clearance_summary' => json_encode($summary['fees']),
                'joining_kit_summary' => json_encode($summary['joining_kit']),
                'orientation_status' => $summary['orientation_status'],
                'handoff_notes' => $summary['notes'],
                'metadata' => json_encode(['updated_by' => $actor?->id, 'v' => '0.039']),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return DB::table('admission_handoff_records')->where('applicant_id', $applicant->id)->first();
    }

    public function evaluate(Applicant $applicant, ?EnrollmentConfirmation $confirmation = null): array
    {
        $mandatory = RequiredDocument::where('program_id', $applicant->program_id)->where('is_mandatory', true)->where('is_active', true)->get();
        $verifiedMandatory = $applicant->documents()->whereIn('required_document_id', $mandatory->pluck('id'))->where('status', 'verified')->count();
        $verifiedPayments = $applicant->payments()->where('status', 'verified')->count();
        $joiningTotal = DB::table('admission_joining_kit_tasks')->where('applicant_id', $applicant->id)->count();
        $joiningDone = DB::table('admission_joining_kit_tasks')->where('applicant_id', $applicant->id)->where('status', 'completed')->count();
        $blockers = [];

        if ($mandatory->count() > $verifiedMandatory) {
            $blockers[] = 'Mandatory documents are not fully verified.';
        }
        if ($verifiedPayments < 1) {
            $blockers[] = 'Admission fee clearance is not verified.';
        }
        if (! $confirmation) {
            $blockers[] = 'Enrollment confirmation is not generated.';
        }
        if ($confirmation && ! $confirmation->roll_number) {
            $blockers[] = 'Roll number is missing.';
        }
        if ($joiningTotal > 0 && $joiningDone < $joiningTotal) {
            $blockers[] = 'Joining kit has pending tasks.';
        }

        return [
            'status' => $blockers ? ($confirmation ? 'blocked' : 'pending_admission_completion') : 'ready_for_academics',
            'blockers' => $blockers,
            'documents' => ['mandatory_total' => $mandatory->count(), 'verified_mandatory' => $verifiedMandatory],
            'fees' => ['verified_payments' => $verifiedPayments],
            'joining_kit' => ['total' => $joiningTotal, 'completed' => $joiningDone],
            'orientation_status' => $joiningTotal && $joiningDone === $joiningTotal ? 'ready' : 'pending',
            'notes' => $blockers ? 'Handoff requires admission correction.' : 'Ready for Academics/PMC intake.',
        ];
    }

    public function markHandedOff(int $handoffId, User $actor): void
    {
        DB::table('admission_handoff_records')->where('id', $handoffId)->update([
            'status' => 'handed_off',
            'handed_off_by' => $actor->id,
            'handed_off_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function returnForCorrection(int $handoffId, User $actor, string $reason): void
    {
        DB::table('admission_handoff_records')->where('id', $handoffId)->update([
            'status' => 'returned_for_correction',
            'returned_by' => $actor->id,
            'returned_at' => now(),
            'handoff_notes' => $reason,
            'updated_at' => now(),
        ]);
    }
}
