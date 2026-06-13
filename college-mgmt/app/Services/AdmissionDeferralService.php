<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdmissionDeferralService
{
    public function request(Applicant $applicant, int $toBatchId, string $reason): int
    {
        return DB::table('admission_deferrals')->insertGetId([
            'applicant_id' => $applicant->id,
            'from_batch_id' => $applicant->batch_id,
            'to_batch_id' => $toBatchId,
            'reason' => $reason,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function approve(int $deferralId, User $actor, ?string $notes = null): void
    {
        $deferral = DB::table('admission_deferrals')->where('id', $deferralId)->first();
        if (! $deferral) {
            return;
        }

        DB::table('admission_deferrals')->where('id', $deferralId)->update([
            'status' => 'approved',
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'carry_forward_notes' => $notes,
            'updated_at' => now(),
        ]);

        Applicant::where('id', $deferral->applicant_id)->update(['batch_id' => $deferral->to_batch_id, 'updated_at' => now()]);
    }
}
