<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\ProgramSeatMatrix;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdmissionSeatControlService
{
    public function hold(Applicant $applicant, array $data, ?User $actor = null): int
    {
        $programId = $data['program_id'] ?? $applicant->program_id;
        $batchId = $data['batch_id'] ?? $applicant->batch_id;
        $this->assertSeatAvailable($programId, $batchId, $data['category'] ?? null);

        $id = DB::table('admission_seat_holds')->insertGetId([
            'applicant_id' => $applicant->id,
            'offer_round_id' => $data['offer_round_id'] ?? null,
            'program_id' => $programId,
            'batch_id' => $batchId,
            'status' => 'held',
            'held_at' => now(),
            'expires_at' => $data['expires_at'] ?? now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->movement($applicant, 'hold', 'Seat held after offer/selection', $actor);

        return $id;
    }

    public function release(int $holdId, string $reason, ?User $actor = null, bool $promoteWaitlist = true): void
    {
        $hold = DB::table('admission_seat_holds')->where('id', $holdId)->first();
        if (! $hold) {
            return;
        }

        DB::table('admission_seat_holds')->where('id', $holdId)->update([
            'status' => 'released',
            'released_at' => now(),
            'release_reason' => $reason,
            'updated_at' => now(),
        ]);

        $applicant = Applicant::find($hold->applicant_id);
        if ($applicant) {
            $this->movement($applicant, 'release', $reason, $actor);
        }

        if ($promoteWaitlist && $hold->program_id && $hold->batch_id) {
            app(AdmissionWaitlistService::class)->promoteNext($hold->program_id, $hold->batch_id, $actor, $reason);
        }
    }

    public function expireHolds(?User $actor = null): int
    {
        $holds = DB::table('admission_seat_holds')->where('status', 'held')->where('expires_at', '<', now())->get();
        foreach ($holds as $hold) {
            $this->release($hold->id, 'Hold expired due to non-payment or non-acceptance', $actor);
        }

        return $holds->count();
    }

    private function movement(Applicant $applicant, string $type, string $reason, ?User $actor): void
    {
        DB::table('admission_seat_movements')->insert([
            'applicant_id' => $applicant->id,
            'program_id' => $applicant->program_id,
            'batch_id' => $applicant->batch_id,
            'movement_type' => $type,
            'reason' => $reason,
            'actor_user_id' => $actor?->id,
            'metadata' => json_encode(['v' => '0.038']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertSeatAvailable(?int $programId, ?int $batchId, ?string $category = null): void
    {
        if (! $programId) {
            return;
        }

        $matrix = ProgramSeatMatrix::where('program_id', $programId)
            ->where(function ($query) use ($batchId) {
                $query->whereNull('batch_id')->when($batchId, fn ($q) => $q->orWhere('batch_id', $batchId));
            })
            ->when($category, fn ($q) => $q->where('category', $category))
            ->first();

        if (! $matrix) {
            return;
        }

        $held = DB::table('admission_seat_holds')
            ->where('program_id', $programId)
            ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
            ->whereIn('status', ['held', 'accepted'])
            ->count();

        if ($held >= (int) $matrix->total_seats) {
            throw ValidationException::withMessages(['seat' => 'Seat matrix capacity is exhausted for this program/batch/category.']);
        }
    }
}
