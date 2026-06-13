<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AdmissionConsentService
{
    public function set(Model $subject, string $channel, string $status, ?User $actor = null, ?string $reason = null, string $source = 'staff'): void
    {
        $existing = DB::table('admission_consent_records')
            ->where('subject_type', get_class($subject))
            ->where('subject_id', $subject->id)
            ->where('channel', $channel)
            ->first();

        DB::table('admission_consent_records')->updateOrInsert(
            ['subject_type' => get_class($subject), 'subject_id' => $subject->id, 'channel' => $channel],
            [
                'status' => $status,
                'source' => $source,
                'reason' => $reason,
                'consented_at' => now(),
                'recorded_by' => $actor?->id,
                'metadata' => json_encode(['v' => '0.038']),
                'created_at' => $existing?->created_at ?? now(),
                'updated_at' => now(),
            ]
        );

        $record = DB::table('admission_consent_records')
            ->where('subject_type', get_class($subject))
            ->where('subject_id', $subject->id)
            ->where('channel', $channel)
            ->first();

        DB::table('admission_consent_histories')->insert([
            'consent_record_id' => $record->id,
            'from_status' => $existing?->status,
            'to_status' => $status,
            'actor_user_id' => $actor?->id,
            'reason' => $reason,
            'changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function allowed(Model $subject, string $channel): bool
    {
        $record = DB::table('admission_consent_records')
            ->where('subject_type', get_class($subject))
            ->where('subject_id', $subject->id)
            ->where('channel', $channel)
            ->first();

        return ! $record || $record->status === 'opt_in';
    }
}
