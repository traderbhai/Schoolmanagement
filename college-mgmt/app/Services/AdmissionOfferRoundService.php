<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdmissionOfferRoundService
{
    public function create(array $data): int
    {
        return DB::table('admission_offer_rounds')->insertGetId([
            'program_id' => $data['program_id'] ?? null,
            'batch_id' => $data['batch_id'] ?? null,
            'round_number' => $data['round_number'] ?? 1,
            'name' => $data['name'],
            'publish_at' => $data['publish_at'] ?? now(),
            'offer_valid_until' => $data['offer_valid_until'] ?? now()->addDays(7),
            'status' => $data['status'] ?? 'draft',
            'source_type' => $data['source_type'] ?? 'committee',
            'metadata' => json_encode(['v' => '0.038']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function publish(int $roundId, ?User $actor = null): int
    {
        DB::table('admission_offer_rounds')->where('id', $roundId)->update(['status' => 'published', 'publish_at' => now(), 'updated_at' => now()]);
        $round = DB::table('admission_offer_rounds')->where('id', $roundId)->first();
        $applicants = Applicant::where('status', 'selected')->where('program_id', $round->program_id)->limit(20)->get();

        foreach ($applicants as $applicant) {
            app(AdmissionSeatControlService::class)->hold($applicant, [
                'offer_round_id' => $roundId,
                'program_id' => $round->program_id,
                'batch_id' => $round->batch_id,
                'expires_at' => $round->offer_valid_until,
            ], $actor);
        }

        return $applicants->count();
    }
}
