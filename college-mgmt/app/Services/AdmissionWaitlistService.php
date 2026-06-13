<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdmissionWaitlistService
{
    public function add(Applicant $applicant, array $data): int
    {
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
        $entry = DB::table('admission_waitlist_entries')
            ->where('program_id', $programId)
            ->where('batch_id', $batchId)
            ->where('status', 'waiting')
            ->orderBy('rank')
            ->first();

        if (! $entry) {
            return null;
        }

        DB::table('admission_waitlist_entries')->where('id', $entry->id)->update([
            'status' => 'promoted',
            'promoted_at' => now(),
            'promoted_by' => $actor?->id,
            'promotion_reason' => $reason,
            'updated_at' => now(),
        ]);

        $applicant = Applicant::find($entry->applicant_id);
        if ($applicant) {
            app(AdmissionSeatControlService::class)->hold($applicant, ['program_id' => $programId, 'batch_id' => $batchId, 'expires_at' => now()->addDays(5)], $actor);
        }

        return DB::table('admission_waitlist_entries')->where('id', $entry->id)->first();
    }
}
