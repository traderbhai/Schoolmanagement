<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdmissionJoiningKitService
{
    public function ensure(Applicant $applicant, ?User $owner = null): void
    {
        foreach ([
            'final_documents' => 'Final documents verified',
            'fee_clearance' => 'Fee clearance confirmed',
            'orientation' => 'Orientation details shared',
            'id_card' => 'ID card handoff initiated',
            'hostel_transport' => 'Hostel/transport handoff checked',
        ] as $key => $title) {
            DB::table('admission_joining_kit_tasks')->updateOrInsert(
                ['applicant_id' => $applicant->id, 'task_key' => $key],
                [
                    'title' => $title,
                    'status' => $key === 'orientation' ? 'completed' : 'pending',
                    'due_at' => now()->addDays(3),
                    'completed_at' => $key === 'orientation' ? now() : null,
                    'owner_user_id' => $owner?->id,
                    'metadata' => json_encode(['v' => '0.038']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function enrollmentReady(Applicant $applicant): bool
    {
        return ! DB::table('admission_joining_kit_tasks')
            ->where('applicant_id', $applicant->id)
            ->where('status', '!=', 'completed')
            ->exists();
    }
}
