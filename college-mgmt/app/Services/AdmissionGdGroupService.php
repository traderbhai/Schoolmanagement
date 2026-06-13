<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdmissionGdGroupService
{
    public function build(int $panelId, ?int $slotId = null, int $capacity = 8, ?int $moderatorId = null): Collection
    {
        $applicantIds = DB::table('admission_assessment_panel_assignments')
            ->where('panel_id', $panelId)
            ->pluck('applicant_id')
            ->values();

        if ($applicantIds->isEmpty() && $slotId) {
            $applicantIds = DB::table('admission_assessment_slot_assignments')->where('slot_id', $slotId)->pluck('applicant_id')->values();
        }

        return $applicantIds->chunk($capacity)->values()->map(function ($chunk, int $index) use ($panelId, $slotId, $capacity, $moderatorId) {
            $groupId = DB::table('admission_gd_groups')->insertGetId([
                'panel_id' => $panelId,
                'slot_id' => $slotId,
                'group_number' => $index + 1,
                'topic' => 'Leadership dilemma and market-entry discussion',
                'moderator_user_id' => $moderatorId,
                'capacity' => $capacity,
                'status' => 'planned',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($chunk as $applicantId) {
                DB::table('admission_gd_group_members')->updateOrInsert(
                    ['gd_group_id' => $groupId, 'applicant_id' => $applicantId],
                    ['lifecycle_status' => 'invited', 'created_at' => now(), 'updated_at' => now()]
                );
            }

            return DB::table('admission_gd_groups')->where('id', $groupId)->first();
        });
    }
}
